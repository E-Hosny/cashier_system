<template>
  <div class="h-screen flex flex-col" dir="rtl">
    <!-- Header ثابت -->
    <div class="flex-shrink-0 bg-white border-b border-gray-200 p-4">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-extrabold text-gray-800">🍹 واجهة الكاشير</h1>
        <img src="/images/mylogo.png" alt="Logo" class="w-24" />
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex overflow-hidden">
      <!-- الفئات - ثابتة -->
      <div class="w-64 bg-gray-50 border-l border-gray-200 flex-shrink-0">
        <div class="p-4">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">📋 الفئات</h3>
          <div class="space-y-2">
            <div
              class="cursor-pointer px-4 py-3 bg-blue-100 hover:bg-blue-200 rounded-lg text-center font-bold text-blue-800 shadow transition-colors"
              :class="{ 'bg-blue-300': selectedCategoryId === null }"
              @click="selectCategory(null)"
            >📋 كل المنتجات</div>

            <div
              v-for="cat in categories"
              :key="cat.id"
              class="cursor-pointer px-4 py-3 bg-white hover:bg-gray-100 rounded-lg text-center font-semibold shadow transition-colors border border-gray-200"
              :class="{ 'bg-green-200 border-green-300': selectedCategoryId === cat.id }"
              @click="selectCategory(cat.id)"
            >{{ cat.name }}</div>
          </div>
        </div>
      </div>

      <!-- المنتجات - قابلة للتمرير -->
      <div class="flex-1 flex flex-col overflow-hidden">
        <!-- شريط البحث ثابت -->
        <div class="flex-shrink-0 p-4 bg-white border-b border-gray-200">
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="ابحث عن عصير..." 
            class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
          />
        </div>

        <!-- قائمة المنتجات - قابلة للتمرير -->
        <div class="flex-1 overflow-y-auto p-4">
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4">
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              class="bg-white rounded-lg shadow-lg overflow-hidden transform transition-all hover:scale-105 flex flex-col border border-gray-200 text-sm"
            >
              <!-- <div class="relative w-full h-32">
                <img v-if="product.image" :src="`/storage/${product.image}`" alt="صورة المنتج" class="w-full h-full object-contain rounded-t-lg" />
                <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center rounded-t-lg">
                  <span class="text-gray-400">🖼️</span>
                </div>
              </div> -->
              <div class="p-3 flex-1 flex flex-col justify-between">
                <h3 class="text-sm font-semibold text-gray-800 text-center leading-tight">{{ product.name }}</h3>
                
                <!-- Size Selection -->
                <div v-if="hasVariants(product)" class="my-2 flex justify-center gap-1">
                    <button 
                      v-for="(variant, v_idx) in product.size_variants" 
                      :key="variant.size"
                      @click="selectVariant(product, v_idx)"
                      :class="['px-2 py-1 rounded-full text-xs font-semibold', product.selectedVariantIndex === v_idx ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700']"
                    >
                      {{ translateSize(variant.size) }}
                    </button>
                </div>

                <p class="text-center text-green-700 text-base font-bold mb-2">
                  {{ getProductPrice(product) }}
                </p>

                <div class="mt-auto text-center">
                  <input v-model.number="product.quantityToAdd" type="number" min="1" placeholder="العدد" class="p-2 border border-gray-300 rounded-lg text-center w-full text-sm" />
                  <button @click="addToCart(product)" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg transition mt-2 w-full text-sm">إضافة للسلة</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- السلة - ثابتة -->
      <div class="w-80 bg-gray-100 border-r border-gray-200 flex-shrink-0 flex flex-col">
        <div class="p-4 border-b border-gray-200">
          <h2 class="text-xl font-semibold text-end">🛒 السلة</h2>
        </div>
        
        <!-- محتوى السلة - قابل للتمرير -->
        <div class="flex-1 overflow-y-auto p-4">
          <div v-if="cart.length === 0" class="text-center text-gray-500 py-8">
              السلة فارغة حالياً.
          </div>
          <div v-for="(item, index) in cart" :key="item.cartItemId" class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
            <div class="text-right w-full sm:w-auto">
              <span class="font-medium text-sm">{{ item.name }}</span>
              <span v-if="item.size" class="text-xs text-gray-600 block">({{ translateSize(item.size) }})</span> 
              <br>
              <span class="text-green-600 font-bold">{{ item.price }} جنيه</span>
            </div>
            <div class="flex items-center gap-2 self-end sm:self-center">
              <button @click="updateQuantity(index, -1)" :disabled="item.quantity <= 1" class="bg-yellow-500 text-white w-7 h-7 rounded-full transition disabled:opacity-50 text-sm">-</button>
              <span class="text-gray-700 font-bold w-8 text-center text-sm">{{ item.quantity }}</span>
              <button @click="updateQuantity(index, 1)" class="bg-yellow-500 text-white w-7 h-7 rounded-full transition text-sm">+</button>
              <button @click="removeFromCart(index)" class="bg-red-500 text-white w-7 h-7 rounded-full transition mr-2 text-sm">×</button>
            </div>
          </div>
        </div>

        <!-- أزرار الدفع - ثابتة -->
        <div class="p-4 border-t border-gray-200 bg-white">
          <div class="mb-4">
            <p class="font-bold text-xl text-end">الإجمالي: {{ totalAmount }} جنيه</p>
          </div>

          <button 
            @click="checkout" 
            :disabled="cart.length === 0 || isCheckoutLoading" 
            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition disabled:bg-gray-400 flex items-center justify-center gap-2"
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
    checkout() {
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
        payment_method: 'cash'
      };

      // تحسين الأداء: إرسال البيانات بشكل محسن
      axios.post('/store-order', checkoutData, {
        timeout: 10000, // timeout 10 ثوانٍ
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        }
      })
        .then(response => {
          this.orderId = response.data.order_id;
          this.clearCart();
          
          // تحسين الأداء: تقليل وقت الانتظار قبل الطباعة
          setTimeout(() => {
            this.printInvoice();
          }, 100);
        })
        .catch(error => {
          console.error('خطأ أثناء إصدار الفاتورة:', error.response?.data || error.message);
          alert('حدث خطأ: ' + (error.response?.data?.message || 'يرجى مراجعة البيانات'));
        })
        .finally(() => {
          this.isCheckoutLoading = false;
        });
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

