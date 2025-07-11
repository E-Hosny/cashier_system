<template>
  <div class="max-w-[1600px] mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 gap-4">
      <h1 class="text-3xl font-extrabold text-gray-800 text-center sm:text-right">🍹 واجهة الكاشير</h1>
      <img src="/images/mylogo.png" alt="Logo" class="w-32" />
    </div>
    
    <!-- مؤشر حالة الاتصال -->
    <div v-if="!isOnline" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4 text-center">
      <span class="font-bold">⚠️ وضع عدم الاتصال:</span> يمكنك العمل محلياً وطباعة الفواتير. سيتم مزامنة الطلبات عند عودة الإنترنت.
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
          {{ isCheckoutLoading ? 'جاري إصدار الفاتورة...' : (isOnline ? 'إصدار الفاتورة' : 'إصدار فاتورة محلية') }}
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
console.log('تم تحميل Cashier.vue');
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
      isOnline: navigator.onLine,
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
      console.log('بدأت دالة checkout');
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
        console.log('دخلنا في catch بسبب خطأ الشبكة أو السيرفر:', error);
        await this.saveOfflineOrder(checkoutData);
        this.clearCart();
        console.log('سيتم الآن طباعة الفاتورة...');
        this.printOfflineInvoice(checkoutData);
        setTimeout(() => {
          this.showOfflineMessage();
        }, 1000);
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
      alert('تم حفظ الطلب محلياً وطباعة الفاتورة. سيتم مزامنة الطلب مع الخادم عند عودة الإنترنت.\n\nملاحظة: الفاتورة المطبوعة تحتوي على معرف فريد للطلب المحلي.');
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
      // فحص حالة الاتصال عند التحميل
      this.checkConnectionStatus();
      
      window.addEventListener('online', () => {
        console.log('متصل بالإنترنت');
        this.isOnline = true;
        this.syncOfflineOrders();
      });

      window.addEventListener('offline', () => {
        console.log('غير متصل بالإنترنت');
        this.isOnline = false;
      });

      // فحص دوري لحالة الاتصال كل 30 ثانية
      setInterval(() => {
        this.checkConnectionStatus();
      }, 30000);
    },

    async checkConnectionStatus() {
      try {
        // محاولة الوصول إلى نقطة نهاية بسيطة
        await fetch('/api/health-check', { 
          method: 'HEAD',
          cache: 'no-cache',
          timeout: 3000
        });
        this.isOnline = true;
      } catch (error) {
        console.log('لا يوجد اتصال بالإنترنت');
        this.isOnline = false;
      }
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
    },

    // تحسين التعامل مع الأخطاء في الطباعة
    handlePrintError(error) {
      console.error('خطأ في الطباعة:', error);
      alert('حدث خطأ في الطباعة. يرجى المحاولة مرة أخرى أو استخدام Ctrl+P للطباعة اليدوية.');
    },
    printOfflineInvoice(orderData) {
      console.log('تشغيل دالة printOfflineInvoice');
      // إنشاء فاتورة HTML محلية
      const invoiceHTML = this.generateOfflineInvoiceHTML(orderData);
      
      // إنشاء iframe للطباعة بدلاً من window.open()
      const printIframe = document.createElement('iframe');
      printIframe.style.position = 'fixed';
      printIframe.style.top = '-9999px';
      printIframe.style.left = '-9999px';
      printIframe.style.width = '320px';
      printIframe.style.height = '500px';
      document.body.appendChild(printIframe);
      
      // كتابة محتوى الفاتورة في iframe
      const iframeDoc = printIframe.contentDocument || printIframe.contentWindow.document;
      iframeDoc.open();
      iframeDoc.write(invoiceHTML);
      iframeDoc.close();
      
      // انتظار تحميل المحتوى ثم الطباعة
      setTimeout(() => {
        try {
          printIframe.contentWindow.focus();
          printIframe.contentWindow.print();
          
          // إزالة iframe بعد الطباعة
          setTimeout(() => {
            document.body.removeChild(printIframe);
          }, 2000);
        } catch (error) {
          console.error('فشل في الطباعة:', error);
          
          // محاولة بديلة: إنشاء نافذة جديدة
          try {
            const printWindow = window.open('', '_blank', 'width=320,height=500');
            if (printWindow) {
              printWindow.document.write(invoiceHTML);
              printWindow.document.close();
              
              setTimeout(() => {
                printWindow.print();
                setTimeout(() => {
                  printWindow.close();
                }, 1000);
              }, 500);
            } else {
              // إذا فشلت النافذة المنبثقة، اعرض الفاتورة في نافذة منبثقة
              this.showOfflineInvoiceInModal(invoiceHTML);
            }
          } catch (fallbackError) {
            console.error('فشل في الطباعة البديلة:', fallbackError);
            this.showOfflineInvoiceInModal(invoiceHTML);
          }
          
          // إزالة iframe في جميع الحالات
          setTimeout(() => {
            if (document.body.contains(printIframe)) {
              document.body.removeChild(printIframe);
            }
          }, 2000);
        }
      }, 500);
    },

    showOfflineInvoiceInModal(invoiceHTML) {
      // إنشاء modal لعرض الفاتورة
      const modal = document.createElement('div');
      modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
      `;
      
      const modalContent = document.createElement('div');
      modalContent.style.cssText = `
        background: white;
        padding: 20px;
        border-radius: 10px;
        max-width: 90%;
        max-height: 90%;
        overflow: auto;
        direction: rtl;
      `;
      
      modalContent.innerHTML = `
        <div style="text-align: center; margin-bottom: 20px;">
          <h3 style="color: #e74c3c; margin-bottom: 10px;">⚠️ فاتورة محلية</h3>
          <p style="color: #666; margin-bottom: 15px;">تم إنشاء هذه الفاتورة بدون إنترنت</p>
          <button onclick="window.print()" style="
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
          ">طباعة الفاتورة</button>
          <button onclick="this.parentElement.parentElement.parentElement.remove()" style="
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
          ">إغلاق</button>
        </div>
        <div style="border-top: 1px solid #eee; padding-top: 20px;">
          ${invoiceHTML}
        </div>
      `;
      
      modal.appendChild(modalContent);
      document.body.appendChild(modal);
      
      // إغلاق modal عند الضغط على ESC
      const handleEscape = (e) => {
        if (e.key === 'Escape') {
          document.body.removeChild(modal);
          document.removeEventListener('keydown', handleEscape);
        }
      };
      document.addEventListener('keydown', handleEscape);
    },

    generateOfflineInvoiceHTML(orderData) {
      const now = new Date();
      const orderId = orderData.offline_id;
      const total = parseFloat(orderData.total_price);
      
      return `
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <title>فاتورة محلية</title>
            <style>
                @page {
                    size: 80mm 297mm;
                    margin: 5mm;
                }
                body { 
                    font-family: Arial, sans-serif; 
                    direction: rtl; 
                    padding: 5px; 
                    margin: 0;
                    font-size: 12px;
                    max-width: 70mm;
                    margin: 0 auto;
                    line-height: 1.3;
                }
                table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin-top: 10px; 
                    font-size: 11px;
                }
                th, td { 
                    border: 1px solid #000; 
                    padding: 4px; 
                    text-align: right; 
                    font-size: 10px;
                }
                th { 
                    background: #eee; 
                    font-weight: bold;
                    font-size: 11px;
                }
                .total { 
                    margin-top: 10px; 
                    font-weight: bold; 
                    font-size: 14px; 
                    text-align: center;
                    border-top: 2px solid #000;
                    padding-top: 5px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 10px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 10px;
                }
                .invoice-title {
                    font-size: 16px;
                    font-weight: bold;
                    margin: 5px 0;
                }
                .invoice-date {
                    font-size: 11px;
                    color: #666;
                }
                .offline-notice {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    padding: 5px;
                    margin: 8px 0;
                    border-radius: 3px;
                    font-size: 9px;
                    text-align: center;
                }
                .logo-placeholder {
                    width: 60px;
                    height: 30px;
                    background: #f0f0f0;
                    margin: 0 auto 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid #ddd;
                    font-size: 8px;
                    color: #666;
                }
                .order-id {
                    text-align: center;
                    margin: 8px 0;
                    font-size: 9px;
                    color: #666;
                    background: #f8f9fa;
                    padding: 3px;
                    border-radius: 2px;
                }
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #eee;
                    padding-top: 8px;
                }
                @media print {
                    body { 
                        margin: 0; 
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo-placeholder">شعار المتجر</div>
                <div class="invoice-title">فاتورة رقم #${orderId}</div>
                <div class="invoice-date">التاريخ: ${now.toLocaleDateString('ar-SA')} ${now.toLocaleTimeString('ar-SA')}</div>
                <div class="offline-notice">⚠️ فاتورة محلية - سيتم مزامنتها عند عودة الإنترنت</div>
                <div class="order-id">معرف الطلب: ${orderId}</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>المنتج</th>
                        <th>الكمية</th>
                        <th>السعر</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    ${orderData.items.map(item => `
                        <tr>
                            <td>${item.product_name}${item.size ? ` (${item.size})` : ''}</td>
                            <td>${item.quantity}</td>
                            <td>${parseFloat(item.price).toFixed(2)}</td>
                            <td>${(item.quantity * parseFloat(item.price)).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <div class="total">الإجمالي الكلي: ${total.toFixed(2)} جنيه</div>
            
            <div class="footer">
                <div style="margin-bottom: 5px;">شكراً لزيارتكم 🌟</div>
                <div style="font-size: 8px; color: #999;">تم إنشاء هذه الفاتورة في وضع عدم الاتصال</div>
            </div>
        </body>
        </html>
      `;
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

