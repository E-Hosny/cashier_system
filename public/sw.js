const CACHE_NAME = "cashier-system-v2";
const OFFLINE_URLS = [
  "/",
  "/cashier",
  "/offline",
  "/dashboard",
  "/sales-report",
  "/purchases",
  "/expenses",
  "/products",
  "/categories",
  "/raw-materials",
  "/users",
  "/css/app.css",
  "/js/app.js",
  "/images/mylogo.png",
  "/fonts/figtree.woff2",
  "/build/assets/app-CSfH5biG.css",
  "/build/assets/app-tah7u06H.js",
  "/build/assets/Cashier-Mbusu6mB.js"
];

// Install event - cache offline resources
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log("Caching offline resources");
        return cache.addAll(OFFLINE_URLS);
      })
      .catch(error => {
        console.error("Failed to cache resources:", error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log("Deleting old cache:", cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});

// Fetch event - serve from cache when offline
self.addEventListener("fetch", (event) => {
  // Skip non-GET requests
  if (event.request.method !== "GET") {
    return;
  }

  // Skip API requests
  if (event.request.url.includes("/api/")) {
    return;
  }

  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Return cached response if available
        if (response) {
          return response;
        }

        // Try to fetch from network
        return fetch(event.request)
          .then((fetchResponse) => {
            // Cache successful responses
            if (fetchResponse && fetchResponse.status === 200) {
              const responseToCache = fetchResponse.clone();
              caches.open(CACHE_NAME)
                .then((cache) => {
                  cache.put(event.request, responseToCache);
                });
            }
            return fetchResponse;
          })
          .catch(() => {
            // Return offline page for navigation requests
            if (event.request.destination === "document") {
              return caches.match("/offline");
            }
            
            // For images, return a default image or empty response
            if (event.request.destination === "image") {
              return new Response("", { status: 404 });
            }
            
            // Return cached version for other requests
            return caches.match(event.request);
          });
      })
  );
});

// Background sync for offline orders
self.addEventListener("sync", (event) => {
  if (event.tag === "sync-orders") {
    event.waitUntil(syncOrders());
  }
});

// Sync offline orders when back online
async function syncOrders() {
  const offlineOrders = await getOfflineOrders();
  
  for (const order of offlineOrders) {
    try {
      const response = await fetch("/api/offline/store-order", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector("meta[name=\"csrf-token\"]")?.getAttribute("content") || ""
        },
        body: JSON.stringify(order)
      });
      
      if (response.ok) {
        await removeOfflineOrder(order.id);
        console.log("Order synced successfully:", order.id);
      }
    } catch (error) {
      console.error("Failed to sync order:", error);
    }
  }
}

// Store offline order in IndexedDB
async function storeOfflineOrder(order) {
  const db = await openDB();
  const tx = db.transaction("orders", "readwrite");
  const store = tx.objectStore("orders");
  await store.add(order);
}

// Get all offline orders
async function getOfflineOrders() {
  const db = await openDB();
  const tx = db.transaction("orders", "readonly");
  const store = tx.objectStore("orders");
  return await store.getAll();
}

// Remove offline order after successful sync
async function removeOfflineOrder(orderId) {
  const db = await openDB();
  const tx = db.transaction("orders", "readwrite");
  const store = tx.objectStore("orders");
  await store.delete(orderId);
}

// Open IndexedDB
async function openDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open("CashierSystem", 1);
    
    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Create orders store
      if (!db.objectStoreNames.contains("orders")) {
        const orderStore = db.createObjectStore("orders", { keyPath: "id", autoIncrement: true });
        orderStore.createIndex("timestamp", "timestamp", { unique: false });
      }
      
      // Create products cache store
      if (!db.objectStoreNames.contains("products")) {
        const productStore = db.createObjectStore("products", { keyPath: "id" });
      }
    };
  });
}
