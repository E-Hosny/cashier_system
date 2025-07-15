<template>
  <div class="h-screen flex flex-col" dir="rtl">
    <!-- Header ثابت -->
    <div class="flex-shrink-0 bg-white border-b border-gray-200 p-2 px-4">
              <div class="flex justify-between items-center gap-2">
          <h1 class="text-xl font-extrabold text-gray-800">🍹 واجهة الكاشير</h1>
          <div class="flex items-center gap-4">
            <!-- مؤشر حالة الاتصال -->
            <div class="flex items-center gap-2">
              <div :class="[
                'w-3 h-3 rounded-full animate-pulse',
                isOnline ? 'bg-green-500' : 'bg-red-500'
              ]"></div>
              <span class="text-sm font-medium">
                {{ isOnline ? 'متصل' : 'غير متصل' }}
              </span>
            </div>
            
            <!-- زر إدارة الوردية -->
            <div class="flex items-center gap-2">
              <button 
                v-if="!currentShift" 
                @click="showShiftModal = true"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                🕐 بدء وردية
              </button>
              <button 
                v-else 
                @click="showCloseShiftModal = true"
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                🔒 تقفيل الوردية
              </button>
            </div>
            
            <!-- زر إدارة الطلبات في وضع عدم الاتصال -->
            <button 
              @click="goToOfflineOrders"
              class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
            >
              📱 الطلبات المحفوظة
            </button>
            
            <img src="/images/mylogo.png" alt="Logo" class="w-14" />
          </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex overflow-hidden">
      <!-- الفئات - ثابتة مع إمكانية التمرير -->
      <div class="w-64 bg-gray-50 border-l border-gray-200 flex-shrink-0 flex flex-col">
        <div class="p-3 flex-shrink-0">
          <h3 class="text-base font-semibold text-gray-800 mb-3 text-center">📋 الفئات</h3>
        </div>
        <div class="flex-1 overflow-y-auto hover:overflow-y-scroll scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100 px-3 pb-3">
          <div class="space-y-1">
            <div
              class="cursor-pointer px-3 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg text-center font-bold text-blue-800 shadow transition-colors text-sm"
              :class="{ 'bg-blue-300': selectedCategoryId === null }"
              @click="selectCategory(null)"
            >📋 كل المنتجات</div>

            <div
              v-for="cat in categories"
              :key="cat.id"
              class="cursor-pointer px-3 py-2 bg-white hover:bg-gray-100 rounded-lg text-center font-semibold shadow transition-colors border border-gray-200 text-sm"
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
          <h2 class="text-xl font-semibold text-center">🛒 السلة</h2>
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

    <!-- إشعار وضع عدم الاتصال -->
    <div
      v-if="showOfflineNotification"
      class="fixed top-4 right-4 bg-yellow-500 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-pulse"
    >
      <div class="flex items-center gap-2">
        <span>⚠️</span>
        <span>تم حفظ الطلب في وضع عدم الاتصال</span>
      </div>
    </div>

    <!-- نافذة بدء الوردية -->
    <div
      v-if="showShiftModal"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
      @click.self="showShiftModal = false"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-96 max-w-md">
        <h3 class="text-lg font-bold text-gray-800 mb-4 text-center">بدء وردية جديدة</h3>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">نوع الوردية</label>
            <select v-model="newShiftType" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="morning">وردية صباحية</option>
              <option value="evening">وردية مسائية</option>
            </select>
          </div>
          
          <div class="flex gap-3">
            <button 
              @click="startShift"
              :disabled="isStartingShift"
              class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition disabled:bg-gray-400"
            >
              {{ isStartingShift ? 'جاري البدء...' : 'بدء الوردية' }}
            </button>
            <button 
              @click="showShiftModal = false"
              class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition"
            >
              إلغاء
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- نافذة تقفيل الوردية -->
    <div
      v-if="showCloseShiftModal"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
      @click.self="showCloseShiftModal = false"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-96 max-w-md">
        <h3 class="text-lg font-bold text-gray-800 mb-4 text-center">تأكيد تقفيل الوردية</h3>
        
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
          <p class="text-sm text-yellow-800">
            هل أنت متأكد من رغبتك في تقفيل الوردية؟ سيتم حساب إجمالي المبيعات وعرضها للمراجعة.
          </p>
        </div>
        
        <div class="flex gap-3">
          <button 
            @click="confirmCloseShift"
            :disabled="isClosingShift"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition disabled:bg-gray-400"
          >
            {{ isClosingShift ? 'جاري التقفيل...' : 'تأكيد التقفيل' }}
          </button>
          <button 
            @click="showCloseShiftModal = false"
            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition"
          >
            إلغاء
          </button>
        </div>
      </div>
    </div>

    <!-- نافذة تفاصيل المبيعات -->
    <div
      v-if="showSalesModal"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
      @click.self="showSalesModal = false"
    >
      <div class="bg-white rounded-lg shadow-lg p-6 w-[600px] max-h-[80vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-gray-800 mb-4 text-center">تفاصيل المبيعات - {{ closedShift?.shift_type === 'morning' ? 'وردية صباحية' : 'وردية مسائية' }}</h3>
        
        <div v-if="closedShift" class="space-y-4">
          <!-- ملخص المبيعات -->
          <div class="grid grid-cols-2 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
              <h4 class="font-semibold text-blue-800">إجمالي المبيعات</h4>
              <p class="text-2xl font-bold text-blue-600">{{ closedShift.total_sales }} جنيه</p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
              <h4 class="font-semibold text-green-800">المبلغ المتوقع</h4>
              <p class="text-2xl font-bold text-green-600">{{ closedShift.expected_amount }} جنيه</p>
            </div>
          </div>

          <!-- إدخال المبلغ النقدي -->
          <div class="bg-yellow-50 p-4 rounded-lg">
            <label class="block text-sm font-medium text-yellow-800 mb-2">المبلغ النقدي الموجود في الصندوق</label>
            <input 
              v-model.number="cashAmount" 
              type="number" 
              step="0.01"
              class="w-full p-3 border border-yellow-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"
              placeholder="أدخل المبلغ النقدي"
            />
            <div v-if="cashAmount > 0" class="mt-2">
              <p class="text-sm">
                <span class="font-semibold">الفرق:</span> 
                <span :class="getDifferenceClass()">{{ getDifference() }} جنيه</span>
              </p>
            </div>
          </div>

          <!-- ملاحظات -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات (اختياري)</label>
            <textarea 
              v-model="shiftNotes" 
              rows="3"
              class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="أضف أي ملاحظات هنا..."
            ></textarea>
          </div>

          <!-- تفاصيل المبيعات -->
          <div v-if="salesDetails.length > 0">
            <h4 class="font-semibold text-gray-800 mb-2">تفاصيل المبيعات</h4>
            <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-lg">
              <div v-for="order in salesDetails" :key="order.id" class="p-3 border-b border-gray-100">
                <div class="flex justify-between items-center">
                  <span class="font-medium">طلب #{{ order.id }}</span>
                  <span class="text-green-600 font-bold">{{ order.total }} جنيه</span>
                </div>
                <div class="text-sm text-gray-600 mt-1">
                  {{ new Date(order.created_at).toLocaleString('ar-EG') }}
                </div>
              </div>
            </div>
          </div>

          <!-- أزرار التحكم -->
          <div class="flex gap-3 pt-4">
            <button 
              @click="handOverShift"
              :disabled="!cashAmount || isHandingOver"
              class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition disabled:bg-gray-400"
            >
              {{ isHandingOver ? 'جاري التسليم...' : 'تم التسليم' }}
            </button>
            <button 
              @click="showSalesModal = false"
              class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition"
            >
              إغلاق
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import OfflineManager from '@/offline-manager.js';

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
      // متغيرات إدارة الورديات
      currentShift: null,
      showShiftModal: false,
      showCloseShiftModal: false,
      showSalesModal: false,
      newShiftType: 'morning',
      isStartingShift: false,
      isClosingShift: false,
      isHandingOver: false,
      closedShift: null,
      cashAmount: 0,
      shiftNotes: '',
      salesDetails: [],
      
      // متغيرات حالة الاتصال
      isOnline: true,
      connectionCheckInterval: null,
      showOfflineNotification: false,
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
        payment_method: 'cash'
      };

      try {
        // فحص الاتصال قبل محاولة إنشاء الطلب
        await this.checkConnection();
        console.log('حالة الاتصال:', this.isOnline);
        
        // التحقق من حالة الاتصال أولاً
        if (!this.isOnline) {
          console.log('محاولة إنشاء طلب أوفلاين...');
          // إذا كان غير متصل، أنشئ طلب أوفلاين مباشرة
          const offlineResponse = await axios.post('/offline/orders', checkoutData);
          console.log('استجابة طلب الأوفلاين:', offlineResponse.data);
          if (offlineResponse.data.success) {
            this.orderId = offlineResponse.data.offline_id;
            this.clearCart();
            // طباعة الفاتورة مباشرة بدون رسالة تأكيد - مثل الوضع العادي
            this.printOfflineInvoice(offlineResponse.data);
          } else {
            alert('فشل في إنشاء الطلب في وضع عدم الاتصال: ' + offlineResponse.data.message);
          }
          return;
        }

        // إذا كان متصل، حاول إنشاء طلب عادي
        const response = await axios.post('/store-order', checkoutData, {
          timeout: 10000, // timeout 10 ثوانٍ
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });

        if (response.data.is_offline) {
          // الطلب تم إنشاؤه في وضع عدم الاتصال
          this.orderId = response.data.offline_id;
          this.clearCart();
          
          // طباعة الفاتورة مباشرة بدون رسالة تأكيد - مثل الوضع العادي
          this.printOfflineInvoice(response.data);
        } else {
          // الطلب تم إنشاؤه بشكل طبيعي
          this.orderId = response.data.order_id;
          this.clearCart();
          
          // تحسين الأداء: تقليل وقت الانتظار قبل الطباعة
          setTimeout(() => {
            this.printInvoice();
          }, 100);
        }
      } catch (error) {
        console.error('خطأ أثناء إصدار الفاتورة:', error);
        console.error('تفاصيل الخطأ:', {
          code: error.code,
          message: error.message,
          response: error.response?.data,
          status: error.response?.status
        });
        
        // إذا كان الخطأ بسبب عدم الاتصال، حاول إنشاء طلب في وضع عدم الاتصال
        if (error.code === 'NETWORK_ERROR' || error.message.includes('Network Error') || error.code === 'ERR_NETWORK' || error.code === 'NS_ERROR_OFFLINE' || error.code === 'ERR_INTERNET_DISCONNECTED') {
          console.log('محاولة إنشاء طلب أوفلاين بعد فشل الطلب العادي...');
          try {
            const offlineResponse = await axios.post('/offline/orders', checkoutData);
            console.log('استجابة طلب الأوفلاين (بعد فشل):', offlineResponse.data);
            if (offlineResponse.data.success) {
              this.orderId = offlineResponse.data.offline_id;
              this.clearCart();
              // طباعة الفاتورة مباشرة بدون رسالة تأكيد - مثل الوضع العادي
              this.printOfflineInvoice(offlineResponse.data);
            } else {
              alert('فشل في إنشاء الطلب في وضع عدم الاتصال: ' + offlineResponse.data.message);
            }
          } catch (offlineError) {
            console.error('خطأ في إنشاء طلب أوفلاين:', offlineError);
            console.error('تفاصيل خطأ الأوفلاين:', {
              code: offlineError.code,
              message: offlineError.message,
              response: offlineError.response?.data,
              status: offlineError.response?.status
            });
            alert('حدث خطأ في إنشاء الطلب: ' + (offlineError.response?.data?.message || 'يرجى مراجعة البيانات'));
          }
        } else {
          alert('حدث خطأ: ' + (error.response?.data?.message || 'يرجى مراجعة البيانات'));
        }
      } finally {
        this.isCheckoutLoading = false;
      }
    },

    // طباعة فاتورة في وضع عدم الاتصال
    printOfflineInvoice(orderData) {
      this.showOfflineNotification = true;
      setTimeout(() => {
        this.showOfflineNotification = false;
      }, 3000);
      this.iframeVisible = true;
      this.$nextTick(() => {
        const iframe = document.getElementById('invoice-frame');
        if (iframe) {
          iframe.onload = () => {
            console.log('تم تحميل فاتورة الأوفلاين - الطباعة ستتم تلقائياً');
          };
          // بناء صفوف المنتجات
          let itemsHtml = '';
          for (const item of this.cart) {
            const sizeText = item.size ? '(' + item.size + ')' : '';
            itemsHtml += '<tr><td>' + item.name + ' ' + sizeText + '</td><td>' + item.quantity + '</td><td>' + item.price + ' ريال</td><td>' + (item.quantity * item.price).toFixed(2) + ' ريال</td></tr>';
          }
          // بناء الفاتورة
          const invoiceHtml = '<!DOCTYPE html>' +
            '<html dir="rtl">' +
            '<head>' +
              '<meta charset="UTF-8">' +
              '<title>فاتورة - ' + orderData.invoice_number + '</title>' +
              '<style>' +
                'body{font-family:Arial,sans-serif;margin:0;padding:20px}.header{text-align:center;margin-bottom:20px}.logo{width:80px;height:80px}.items-table{width:100%;border-collapse:collapse;margin-bottom:20px}.items-table th,.items-table td{border:1px solid #ddd;padding:8px;text-align:right}.total{font-weight:bold;font-size:18px;text-align:left}.footer{margin-top:30px;text-align:center;font-size:12px;color:#666}.offline-notice{background:#fff3cd;border:1px solid #ffeaa7;padding:5px;margin:10px 0;border-radius:4px;text-align:center;font-size:12px;color:#856404}@media print{body{margin:0}}' +
              '</style>' +
            '</head>' +
            '<body>' +
              '<div class="header">' +
                '<img src="/images/mylogo.png" alt="Logo" class="logo">' +
                '<h1>فاتورة مبيعات</h1>' +
                '<p>رقم الفاتورة: ' + orderData.invoice_number + '</p>' +
                '<p>التاريخ: ' + new Date().toLocaleDateString('ar-SA') + '</p>' +
                '<p>الوقت: ' + new Date().toLocaleTimeString('ar-SA') + '</p>' +
                '<div class="offline-notice">⚠️ تم إنشاء هذه الفاتورة في وضع عدم الاتصال</div>' +
              '</div>' +
              '<table class="items-table">' +
                '<thead>' +
                  '<tr>' +
                    '<th>المنتج</th>' +
                    '<th>الكمية</th>' +
                    '<th>السعر</th>' +
                    '<th>الإجمالي</th>' +
                  '</tr>' +
                '</thead>' +
                '<tbody>' + itemsHtml + '</tbody>' +
              '</table>' +
              '<div class="total">' +
                '<p>الإجمالي: ' + this.totalAmount + ' ريال</p>' +
                '<p>طريقة الدفع: نقداً</p>' +
              '</div>' +
              '<div class="footer">' +
                '<p>شكراً لزيارتكم</p>' +
                '<p>سيتم مزامنة هذه الفاتورة عند عودة الاتصال</p>' +
              '</div>' +
              '<scr' + 'ipt>window.onload=function(){setTimeout(function(){window.print()},500)}</scr' + 'ipt>' +
            '</body>' +
            '</html>';
          iframe.src = 'data:text/html;charset=utf-8,' + encodeURIComponent(invoiceHtml);
        }
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
    },

    // === إدارة الورديات ===
    
    // بدء وردية جديدة
    async startShift() {
      this.isStartingShift = true;
      
      try {
        const response = await axios.post('/cashier-shifts/start', {
          shift_type: this.newShiftType
        });
        
        if (response.data.success) {
          this.currentShift = response.data.shift;
          this.showShiftModal = false;
          this.newShiftType = 'morning';
          alert('تم بدء الوردية بنجاح!');
        }
      } catch (error) {
        console.error('خطأ في بدء الوردية:', error);
        alert('حدث خطأ: ' + (error.response?.data?.message || 'فشل في بدء الوردية'));
      } finally {
        this.isStartingShift = false;
      }
    },

    // تأكيد تقفيل الوردية
    async confirmCloseShift() {
      this.isClosingShift = true;
      
      try {
        const response = await axios.post('/cashier-shifts/close', {
          cash_amount: 0, // سيتم تحديثه لاحقاً
          notes: ''
        });
        
        if (response.data.success) {
          this.closedShift = response.data.shift;
          this.salesDetails = response.data.sales_details || [];
          this.showCloseShiftModal = false;
          this.showSalesModal = true;
          this.currentShift = null;
        }
      } catch (error) {
        console.error('خطأ في تقفيل الوردية:', error);
        alert('حدث خطأ: ' + (error.response?.data?.message || 'فشل في تقفيل الوردية'));
      } finally {
        this.isClosingShift = false;
      }
    },

    // تسليم الوردية
    async handOverShift() {
      if (!this.cashAmount) {
        alert('يرجى إدخال المبلغ النقدي');
        return;
      }

      this.isHandingOver = true;
      
      try {
        // تحديث الوردية بالمبلغ النقدي أولاً
        await axios.put(`/cashier-shifts/${this.closedShift.id}/update-cash`, {
          cash_amount: this.cashAmount,
          notes: this.shiftNotes
        });

        // تسليم الوردية
        const response = await axios.post('/cashier-shifts/handover');
        
        if (response.data.success) {
          this.showSalesModal = false;
          this.closedShift = null;
          this.cashAmount = 0;
          this.shiftNotes = '';
          this.salesDetails = [];
          alert('تم تسليم الوردية بنجاح!');
        }
      } catch (error) {
        console.error('خطأ في تسليم الوردية:', error);
        alert('حدث خطأ: ' + (error.response?.data?.message || 'فشل في تسليم الوردية'));
      } finally {
        this.isHandingOver = false;
      }
    },

    // الحصول على الوردية الحالية
    async getCurrentShift() {
      try {
        const response = await axios.get('/cashier-shifts/current');
        if (response.data.success) {
          this.currentShift = response.data.shift;
        }
      } catch (error) {
        // لا توجد وردية نشطة
        this.currentShift = null;
      }
    },

    // حساب الفرق بين النقدي والمتوقع
    getDifference() {
      if (!this.closedShift || !this.cashAmount) return 0;
      return (this.cashAmount - this.closedShift.expected_amount).toFixed(2);
    },

    // الحصول على لون الفرق
    getDifferenceClass() {
      const difference = parseFloat(this.getDifference());
      if (difference > 0) return 'text-green-600 font-bold';
      if (difference < 0) return 'text-red-600 font-bold';
      return 'text-gray-600 font-bold';
    },

    // الانتقال إلى صفحة الطلبات في وضع عدم الاتصال
    goToOfflineOrders() {
      this.$inertia.visit('/offline');
    },

    // التحقق من حالة الاتصال
    async checkConnection() {
      try {
        const response = await axios.get('/offline/check-connection', {
          timeout: 5000 // timeout 5 ثوانٍ
        });
        const wasOffline = !this.isOnline;
        this.isOnline = response.data.isOnline;
        
        // إذا كان متصل الآن وكان غير متصل سابقاً، قم بالمزامنة التلقائية
        if (this.isOnline && wasOffline) {
          console.log('تم استعادة الاتصال - بدء المزامنة التلقائية...');
          await this.autoSyncOfflineOrders();
        }
      } catch (error) {
        console.log('خطأ في فحص الاتصال:', error.message);
        this.isOnline = false;
      }
    },

    // المزامنة التلقائية للطلبات في وضع عدم الاتصال
    async autoSyncOfflineOrders() {
      try {
        console.log('بدء المزامنة التلقائية...');
        const response = await axios.post('/offline/sync');
        
        if (response.data.success) {
          const syncedCount = response.data.synced_count || 0;
          if (syncedCount > 0) {
            // عرض إشعار للمستخدم
            this.showNotification(`تم مزامنة ${syncedCount} طلب تلقائياً بنجاح!`, 'success');
          }
        } else {
          console.error('فشل في المزامنة التلقائية:', response.data.message);
        }
      } catch (error) {
        console.error('خطأ في المزامنة التلقائية:', error);
      }
    },

    // عرض إشعار للمستخدم
    showNotification(message, type = 'info') {
      // إنشاء عنصر الإشعار
      const notification = document.createElement('div');
      notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
      }`;
      notification.innerHTML = `
        <div class="flex items-center justify-between">
          <span>${message}</span>
          <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">×</button>
        </div>
      `;
      
      document.body.appendChild(notification);
      
      // إزالة الإشعار تلقائياً بعد 5 ثوانٍ
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 5000);
    },

    // بدء فحص الاتصال الدوري
    startConnectionCheck() {
      this.connectionCheckInterval = setInterval(() => {
        this.checkConnection();
      }, 10000); // فحص كل 10 ثوانٍ
    },

    // إيقاف فحص الاتصال
    stopConnectionCheck() {
      if (this.connectionCheckInterval) {
        clearInterval(this.connectionCheckInterval);
        this.connectionCheckInterval = null;
      }
    },
  },
  mounted() {
    this.initializeProducts();
    document.addEventListener('keydown', this.handleEscape);
    window.addEventListener('message', this.handleIframeMessage);
    
    // تحسين الأداء: تحميل الصورة مسبقاً
    this.preloadInvoiceImage();
    
    // الحصول على الوردية الحالية
    this.getCurrentShift();
    
    // بدء فحص الاتصال
    this.checkConnection();
    this.startConnectionCheck();
  },
  beforeDestroy() {
    document.removeEventListener('keydown', this.handleEscape);
    window.removeEventListener('message', this.handleIframeMessage);
    this.stopConnectionCheck();
  },
  watch: {
      products() {
          this.initializeProducts();
      }
  }
};
</script>

