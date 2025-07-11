<template>
  <div class="max-w-[1600px] mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
      <h1 class="text-3xl font-extrabold text-gray-800 text-center sm:text-right">🍹 واجهة الكاشير</h1>
      <img src="/images/mylogo.png" alt="Logo" class="w-32" />
    </div>

    <div class="flex flex-col lg:flex-row gap-6">
      <!-- ✅ الفئات -->
      <div class="w-full lg:w-1/5 order-3 lg:order-1">
        <div class="space-y-3">
          <div
            class="cursor-pointer px-4 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg text-center font-bold text-blue-800 shadow"
            :class="{ 'bg-blue-300': selectedCategoryId === null }"
            @click="selectCategory(null)"
          >📋 كل المنتجات</div>

          <div
            v-for="cat in categories"
            :key="cat.id"
            class="cursor-pointer px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg text-center font-semibold shadow"
            :class="{ 'bg-green-200': selectedCategoryId === cat.id }"
            @click="selectCategory(cat.id)"
          >{{ cat.name }}</div>
        </div>
      </div>

      <!-- ✅ المنتجات -->
      <div class="w-full lg:w-3/5 order-1 lg:order-2">
        <div class="mb-4">
          <input v-model="searchQuery" type="text" placeholder="ابحث عن عصير..." class="w-full p-3 border border-gray-300 rounded-lg" />
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-4 gap-4 mb-6">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            class="bg-white rounded-lg shadow-lg overflow-hidden transform transition-all hover:scale-105 flex flex-col border border-gray-200 text-sm"
          >
            <div class="relative w-full h-36">
              <img v-if="product.image" :src="`/storage/${product.image}`" alt="صورة المنتج" class="w-full h-full object-contain rounded-t-lg" />
            </div>
            <div class="p-3 flex-1 flex flex-col justify-between">
              <h3 class="text-base font-semibold text-gray-800 text-center">{{ product.name }}</h3>
              
              <!-- Size Selection -->
              <div v-if="hasVariants(product)" class="my-2 flex justify-center gap-2">
                  <button 
                    v-for="(variant, v_idx) in product.size_variants" 
                    :key="variant.size"
                    @click="selectVariant(product, v_idx)"
                    :class="['px-3 py-1 rounded-full text-xs font-semibold', product.selectedVariantIndex === v_idx ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700']"
                  >
                    {{ translateSize(variant.size) }}
                  </button>
              </div>

              <p class="text-center text-green-700 text-lg font-bold mb-2">
                {{ getProductPrice(product) }}
              </p>

              <div class="mt-auto text-center">
                <input v-model.number="product.quantityToAdd" type="number" min="1" placeholder="العدد" class="p-2 border border-gray-300 rounded-lg text-center w-full" />
                <button @click="addToCart(product)" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg transition mt-2 w-full">إضافة للسلة</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ✅ السلة -->
      <div class="w-full lg:w-1/5 bg-gray-100 p-4 rounded-lg shadow-md order-2 lg:order-3">
        <h2 class="text-xl font-semibold text-end mb-4">🛒 السلة</h2>
        <div v-if="cart.length === 0" class="text-center text-gray-500 py-8">
            السلة فارغة حالياً.
        </div>
        <div v-for="(item, index) in cart" :key="item.cartItemId" class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
          <div class="text-right w-full sm:w-auto">
            <span class="font-medium">{{ item.name }}</span>
            <span class="text-xs text-gray-600">({{ translateSize(item.size) }})</span> 
            <br>
            <span class="text-green-600 font-bold">{{ item.price }} جنيه</span>
          </div>
          <div class="flex items-center gap-2 self-end sm:self-center">
            <button @click="updateQuantity(index, -1)" :disabled="item.quantity <= 1" class="bg-yellow-500 text-white w-8 h-8 rounded-full transition disabled:opacity-50">-</button>
            <span class="text-gray-700 font-bold w-8 text-center">{{ item.quantity }}</span>
            <button @click="updateQuantity(index, 1)" class="bg-yellow-500 text-white w-8 h-8 rounded-full transition">+</button>
            <button @click="removeFromCart(index)" class="bg-red-500 text-white w-8 h-8 rounded-full transition mr-2">x</button>
          </div>
        </div>

        <div class="mt-4">
          <p class="font-bold text-xl text-end">الإجمالي: {{ totalAmount }} جنيه</p>
        </div>

        <button 
          @click="checkout" 
          :disabled="cart.length === 0 || isCheckoutLoading" 
          class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg mt-4 transition disabled:bg-gray-400 flex items-center justify-center gap-2"
        >
          <svg v-if="isCheckoutLoading" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isCheckoutLoading ? 'جاري إصدار الفاتورة...' : 'إصدار الفاتورة' }}
        </button>
        <button @click="clearCart" class="w-full bg-red-500 hover:bg-red-600 text-white font-bold py-3 rounded-lg mt-2 transition">تصفير السلة 🗑️</button>
      </div>
    </div>

    <!-- ✅ إطار الطباعة -->
    <div
      v-if="iframeVisible"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
      @click.self="closeIframe"
    >
      <div class="bg-white rounded-lg shadow-lg overflow-hidden w-[320px] h-[500px] p-2">
        <iframe id="invoice-frame" class="w-full h-full" frameborder="0"></iframe>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    products: Array,
    categories: Array,
  },
  data() {
    return {
      searchQuery: '',
      selectedCategoryId: null,
      cart: [],
      orderId: null,
      iframeVisible: false,
      liveProducts: [],
      isCheckoutLoading: false,
      sizeTranslations: {
        small: 'صغير',
        medium: 'وسط',
        large: 'كبير',
      },
    };
  },
  computed: {
    filteredProducts() {
      return this.liveProducts
        .filter(p => this.selectedCategoryId === null || p.category_id === this.selectedCategoryId)
        .filter(p => p.name.toLowerCase().includes(this.searchQuery.toLowerCase()));
    },
    totalAmount() {
      return this.cart.reduce((total, item) => total + item.price * item.quantity, 0).toFixed(2);
    },
  },
  methods: {
    initializeProducts() {
        this.liveProducts = this.products.map(p => ({
            ...p,
            selectedVariantIndex: (p.size_variants && p.size_variants.length > 0) ? 0 : -1, 
            quantityToAdd: 1,
        }));
    },
    hasVariants(product) {
        return product.size_variants && product.size_variants.length > 0;
    },
    getProductPrice(product) {
        if (this.hasVariants(product) && product.selectedVariantIndex !== -1) {
            return `${product.size_variants[product.selectedVariantIndex].price} جنيه`;
        }
        if (product.price) {
            return `${product.price} جنيه`;
        }
        return 'غير مسعر';
    },
    translateSize(size) {
        return this.sizeTranslations[size] || size;
    },
    selectCategory(id) {
      this.selectedCategoryId = id;
    },
    selectVariant(product, variantIndex) {
        product.selectedVariantIndex = variantIndex;
    },
    addToCart(product) {
        const quantity = product.quantityToAdd || 1;

        if (this.hasVariants(product)) {
            const variant = product.size_variants[product.selectedVariantIndex];
            if (!variant) return;
            
            const cartItemId = `${product.id}-${variant.size}`;
            const found = this.cart.find(item => item.cartItemId === cartItemId);

            if (found) {
                found.quantity += quantity;
            } else {
                this.cart.push({
                    cartItemId: cartItemId,
                    product_id: product.id,
                    name: product.name,
                    size: variant.size,
                    price: variant.price,
                    quantity: quantity
                });
            }
        } else {
            const cartItemId = `${product.id}`;
            const found = this.cart.find(item => item.cartItemId === cartItemId);

            if (found) {
                found.quantity += quantity;
            } else {
                this.cart.push({
                    cartItemId: cartItemId,
                    product_id: product.id,
                    name: product.name,
                    size: null,
                    price: product.price || 0,
                    quantity: quantity
                });
            }
        }
      product.quantityToAdd = 1;
    },
    removeFromCart(index) {
      this.cart.splice(index, 1);
    },
    updateQuantity(index, change) {
      const item = this.cart[index];
      item.quantity += change;
      if (item.quantity <= 0) this.removeFromCart(index);
    },
    clearCart() {
      this.cart = [];
    },
    async checkout() {
      this.isCheckoutLoading = true;
      
      const checkoutData = {
        items: this.cart.map(item => ({
          product_id: item.product_id,
          product_name: item.name,
          quantity: item.quantity,
          price: item.price,
          size: item.size
        })),
        total_price: this.totalAmount,
        payment_method: 'cash',
        offline_id: `offline_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
      };

      try {
        // محاولة الإرسال عبر الإنترنت أولاً
        const response = await axios.post('/store-order', checkoutData, {
          timeout: 5000, // timeout 5 ثوانٍ
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });

        this.orderId = response.data.order_id;
        this.clearCart();
        
        setTimeout(() => {
          this.printInvoice();
        }, 100);

      } catch (error) {
        console.log('فشل الاتصال بالخادم، جاري حفظ الطلب محلياً...');
        
        // حفظ الطلب محلياً
        await this.saveOfflineOrder(checkoutData);
        
        this.clearCart();
        this.showOfflineMessage();
      } finally {
        this.isCheckoutLoading = false;
      }
    },

    async saveOfflineOrder(orderData) {
      try {
        // حفظ في IndexedDB
        if ('indexedDB' in window) {
          const db = await this.openDB();
          const tx = db.transaction('orders', 'readwrite');
          const store = tx.objectStore('orders');
          await store.add({
            ...orderData,
            timestamp: Date.now(),
            status: 'pending'
          });
        }

        // حفظ في localStorage كنسخة احتياطية
        const offlineOrders = JSON.parse(localStorage.getItem('offlineOrders') || '[]');
        offlineOrders.push(orderData);
        localStorage.setItem('offlineOrders', JSON.stringify(offlineOrders));

        console.log('تم حفظ الطلب محلياً بنجاح');
      } catch (error) {
        console.error('فشل في حفظ الطلب محلياً:', error);
        throw error;
      }
    },

    async openDB() {
      return new Promise((resolve, reject) => {
        const request = indexedDB.open('CashierSystem', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
          const db = event.target.result;
          
          if (!db.objectStoreNames.contains('orders')) {
            const orderStore = db.createObjectStore('orders', { keyPath: 'id', autoIncrement: true });
            orderStore.createIndex('timestamp', 'timestamp', { unique: false });
          }
        };
      });
    },

    showOfflineMessage() {
      alert('تم حفظ الطلب محلياً. سيتم مزامنته عند عودة الإنترنت.');
    },

    async registerServiceWorker() {
      if ('serviceWorker' in navigator) {
        try {
          const registration = await navigator.serviceWorker.register('/sw.js');
          console.log('Service Worker registered:', registration);
        } catch (error) {
          console.error('Service Worker registration failed:', error);
        }
      }
    },

    monitorConnection() {
      window.addEventListener('online', () => {
        console.log('متصل بالإنترنت');
        this.syncOfflineOrders();
      });

      window.addEventListener('offline', () => {
        console.log('غير متصل بالإنترنت');
      });
    },

    async syncOfflineOrders() {
      try {
        // مزامنة من IndexedDB
        if ('indexedDB' in window) {
          const db = await this.openDB();
          const tx = db.transaction('orders', 'readonly');
          const store = tx.objectStore('orders');
          const offlineOrders = await store.getAll();

          if (offlineOrders.length > 0) {
            for (const order of offlineOrders) {
              try {
                await axios.post('/api/offline/store-order', order);
                // حذف الطلب من IndexedDB بعد المزامنة الناجحة
                const deleteTx = db.transaction('orders', 'readwrite');
                const deleteStore = deleteTx.objectStore('orders');
                await deleteStore.delete(order.id);
              } catch (error) {
                console.error('فشل في مزامنة الطلب:', error);
              }
            }
            console.log('تم مزامنة الطلبات المحفوظة محلياً');
          }
        }

        // مزامنة من localStorage
        const localStorageOrders = JSON.parse(localStorage.getItem('offlineOrders') || '[]');
        if (localStorageOrders.length > 0) {
          for (const order of localStorageOrders) {
            try {
              await axios.post('/api/offline/store-order', order);
            } catch (error) {
              console.error('فشل في مزامنة الطلب من localStorage:', error);
            }
          }
          localStorage.removeItem('offlineOrders');
          console.log('تم مزامنة الطلبات من localStorage');
        }
      } catch (error) {
        console.error('فشل في مزامنة الطلبات:', error);
      }
    },
    printInvoice() {
      this.iframeVisible = true;

      this.$nextTick(() => {
        const iframe = document.getElementById('invoice-frame');
        if (iframe) {
          iframe.onload = () => {
            // الطباعة التلقائية ستتم من داخل الفاتورة HTML
            console.log('تم تحميل الفاتورة - الطباعة ستتم تلقائياً');
          };

          iframe.src = `/invoice-html/${this.orderId}`;
        }
      });
    },
    closeIframe() {
      this.iframeVisible = false;
    },
    handleEscape(e) {
      if (e.key === 'Escape') {
        this.closeIframe();
      }
    },
    handleIframeMessage(e) {
      if (e.data === 'close-iframe') {
        this.closeIframe();
      }
    },
    preloadInvoiceImage() {
      // تحميل صورة الشعار مسبقاً لتسريع عرض الفاتورة
      const img = new Image();
      img.src = '/images/mylogo.png';
    }
  },
  mounted() {
    this.initializeProducts();
    document.addEventListener('keydown', this.handleEscape);
    window.addEventListener('message', this.handleIframeMessage);
    
    // تحسين الأداء: تحميل الصورة مسبقاً
    this.preloadInvoiceImage();
    
    // تسجيل Service Worker للعمل بدون إنترنت
    this.registerServiceWorker();
    
    // مراقبة حالة الاتصال
    this.monitorConnection();
  },
  beforeDestroy() {
    document.removeEventListener('keydown', this.handleEscape);
    window.removeEventListener('message', this.handleIframeMessage);
  },
  watch: {
      products() {
          this.initializeProducts();
      }
  }
};
</script>

