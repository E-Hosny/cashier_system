<template>
  <div class="h-screen flex flex-col" dir="rtl">
    <!-- Header ثابت -->
    <div class="flex-shrink-0 bg-white border-b border-gray-200 p-2 px-4">
              <div class="flex justify-between items-center gap-2">
          <div class="flex items-center gap-3">
            <img
              v-if="$page.props.tenantBranding?.logoUrl"
              :src="$page.props.tenantBranding.logoUrl"
              :alt="$page.props.tenantBranding?.name || 'الشعار'"
              class="h-10 w-auto max-w-[120px] object-contain"
            />
            <h1 class="text-xl font-extrabold text-gray-800">🍹 واجهة الكاشير</h1>
          </div>
          <div class="flex items-center gap-4">

            
            <!-- زر إدارة الوردية -->
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="openRefundModal"
                class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                ↩️ مرتجع
              </button>
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
              v-if="fridgeSectionEnabled"
              class="cursor-pointer px-3 py-2 rounded-lg text-center font-bold shadow transition-colors text-sm border-2"
              :class="showFridgeView ? 'bg-cyan-500 text-white border-cyan-600' : 'bg-cyan-50 text-cyan-800 border-cyan-300 hover:bg-cyan-100'"
              @click="selectFridgeView()"
            >🧊 التلاجة</div>

            <div
              class="cursor-pointer px-3 py-2 bg-blue-100 hover:bg-blue-200 rounded-lg text-center font-bold text-blue-800 shadow transition-colors text-sm"
              :class="{ 'bg-blue-300': !showFridgeView && selectedCategoryId === null }"
              @click="selectCategory(null)"
            >📋 كل المنتجات</div>

            <div
              v-for="cat in categories"
              :key="cat.id"
              class="cursor-pointer px-3 py-2 bg-white hover:bg-gray-100 rounded-lg text-center font-semibold shadow transition-colors border border-gray-200 text-sm"
              :class="{ 'bg-green-200 border-green-300': !showFridgeView && selectedCategoryId === cat.id }"
              @click="selectCategory(cat.id)"
            >{{ cat.name }}</div>

            <div v-if="offers.length" class="pt-2 mt-2 border-t border-gray-300">
              <p class="text-xs text-gray-500 text-center mb-1">🎁 عروض نشطة</p>
              <div
                v-for="offer in offers"
                :key="offer.id"
                class="px-2 py-1.5 mb-1 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-900 text-center"
              >
                <div class="font-bold">{{ offer.name }}</div>
                <div>{{ offer.offer_price }} جنيه</div>
              </div>
            </div>
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
              v-for="product in displayProducts"
              :key="product.cartKey || product.id"
              class="bg-white rounded-lg shadow-lg overflow-hidden transform transition-all hover:scale-105 flex flex-col border text-sm"
              :class="product.from_fridge ? 'border-cyan-400' : 'border-gray-200'"
            >
              <!-- <div class="relative w-full h-32">
                <img v-if="product.image" :src="`/storage/${product.image}`" alt="صورة المنتج" class="w-full h-full object-contain rounded-t-lg" />
                <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center rounded-t-lg">
                  <span class="text-gray-400">🖼️</span>
                </div>
              </div> -->
              <div class="p-3 flex-1 flex flex-col justify-between">
                <h3 class="text-sm font-semibold text-gray-800 text-center leading-tight">
                  {{ product.name }}
                  <span v-if="product.from_fridge" class="text-cyan-600 text-xs block">
                    تلاجة ({{ product.fridge_quantity }})
                    <span v-if="product.outOfFridgeStock" class="text-red-600"> — نفد</span>
                  </span>
                </h3>
                
                <!-- Size Selection -->
                <div v-if="!product.from_fridge && hasVariants(product)" class="my-2 flex justify-center gap-1">
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
                  <button
                    @click="product.from_fridge ? addFridgeToCart(product) : addToCart(product)"
                    class="text-white px-3 py-1.5 rounded-lg transition mt-2 w-full text-sm"
                    :class="product.from_fridge ? 'bg-cyan-600 hover:bg-cyan-700' : 'bg-blue-500 hover:bg-blue-600'"
                  >إضافة للسلة</button>
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
          <div v-for="(item, index) in cart" :key="item.cartItemId" class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2" :class="item.type === 'offer' ? 'bg-purple-50 -mx-2 px-2 rounded-lg' : ''">
            <div class="text-right w-full sm:w-auto">
              <span class="font-medium text-sm">{{ item.name }}</span>
              <span v-if="item.type === 'offer'" class="text-xs text-purple-700 block">عرض — وفرت {{ item.savings }} جنيه</span>
              <span v-if="item.type === 'offer'" class="text-xs text-gray-500 block">
                {{ item.components.map(c => `${c.quantity}× ${c.product_name}`).join(' + ') }}
              </span>
              <span v-if="item.size" class="text-xs text-gray-600 block">({{ translateSize(item.size) }})</span> 
              <br>
              <span class="text-green-600 font-bold">{{ item.price }} جنيه</span>
              <span v-if="item.type === 'offer' && item.original_total" class="text-xs text-gray-400 line-through mr-1">{{ item.original_total }}</span>
            </div>
            <div v-if="item.type !== 'offer'" class="flex items-center gap-2 self-end sm:self-center">
              <button @click="updateQuantity(index, -1)" :disabled="item.quantity <= 1" class="bg-yellow-500 text-white w-7 h-7 rounded-full transition disabled:opacity-50 text-sm">-</button>
              <span class="text-gray-700 font-bold w-8 text-center text-sm">{{ item.quantity }}</span>
              <button @click="updateQuantity(index, 1)" class="bg-yellow-500 text-white w-7 h-7 rounded-full transition text-sm">+</button>
              <button @click="removeFromCart(index)" class="bg-red-500 text-white w-7 h-7 rounded-full transition mr-2 text-sm">×</button>
            </div>
            <div v-else class="text-xs text-purple-600 self-end">تلقائي</div>
          </div>
        </div>

        <!-- أزرار الدفع - ثابتة -->
        <div class="p-4 border-t border-gray-200 bg-white">
          <div class="mb-4">
            <p class="font-bold text-xl text-end">الإجمالي: {{ totalAmount }} جنيه</p>
            <p v-if="totalSavings > 0" class="text-sm text-purple-700 text-end">وفرت {{ totalSavings.toFixed(2) }} جنيه من العروض 🎁</p>
          </div>

          <div v-if="usesDualPrinters" class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات لطابعة العامل</label>
            <textarea
              v-model="staffNotes"
              rows="2"
              maxlength="1000"
              class="w-full border border-gray-300 rounded-lg p-2 text-sm resize-none"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">تظهر في نسخة العامل فقط ولا تُطبع للزبون.</p>
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



    <!-- نافذة: المنتج متوفر في التلاجة -->
    <div
      v-if="showFridgeAvailableModal"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      @click.self="closeFridgeAvailableModal"
    >
      <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md" dir="rtl">
        <h3 class="text-lg font-bold text-cyan-900 mb-2 text-center">🧊 متوفر في التلاجة</h3>
        <p class="text-gray-700 text-sm mb-3 text-center">
          «{{ fridgePromptLabel }}» متوفر في تلاجة الفرع
          <span class="font-bold text-cyan-800">({{ fridgePromptEntry?.fridge_quantity }} وحدة)</span>.
          يُفضَّل البيع من خانة التلاجة لخصم المخزون الصحيح.
        </p>
        <div class="flex flex-col sm:flex-row gap-2">
          <button
            type="button"
            class="flex-1 bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 rounded-lg transition"
            @click="goToFridgeFromPrompt"
          >
            الانتقال للتلاجة
          </button>
          <button
            type="button"
            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition"
            @click="skipFridgePromptAndAdd"
          >
            تخطي والمتابعة
          </button>
        </div>
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

    <!-- نافذة المرتجع -->
    <div
      v-if="showRefundModal"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
      @click.self="closeRefundModal"
    >
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col" dir="rtl">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-lg font-bold text-gray-800">↩️ مرتجع فاتورة</h3>
          <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl leading-none" @click="closeRefundModal">×</button>
        </div>

        <div class="p-5 overflow-y-auto flex-1 space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">ابحث برقم الفاتورة</label>
            <div class="flex flex-col sm:flex-row gap-2">
              <div class="relative flex-1">
                <input
                  v-model="refundSearchQuery"
                  type="text"
                  class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                  placeholder="رقم كامل أو جزء (مثل 260104-042 أو 042)"
                />
                <span
                  v-if="refundLoading"
                  class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-amber-600 pointer-events-none"
                >
                  جاري البحث...
                </span>
              </div>
            </div>
          </div>

          <div v-if="refundSearchResults.length > 0">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">
              نتائج البحث ({{ refundSearchResults.length }})
            </h4>
            <div class="space-y-2 max-h-52 overflow-y-auto">
              <button
                v-for="order in refundSearchResults"
                :key="'search-' + order.id"
                type="button"
                class="w-full text-right p-3 rounded-lg border transition flex justify-between items-center gap-3"
                :class="selectedRefundOrder?.id === order.id ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'"
                @click="selectRefundOrder(order)"
              >
                <div>
                  <div class="font-semibold text-gray-800">#{{ order.invoice_number || order.id }}</div>
                  <div class="text-xs text-gray-500">{{ formatRefundDate(order.created_at) }}</div>
                </div>
                <div class="text-left">
                  <div class="font-bold text-green-700">{{ order.total }} جنيه</div>
                  <span v-if="order.is_refunded" class="text-xs text-red-600">مرتجع</span>
                  <span v-else-if="!order.can_refund" class="text-xs text-gray-500">غير قابل للإرجاع</span>
                </div>
              </button>
            </div>
          </div>

          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-2">فواتير اليوم (الأحدث)</h4>
            <div v-if="refundRecentLoading" class="text-sm text-gray-500 py-4 text-center">جاري التحميل...</div>
            <div v-else-if="refundRecentOrders.length === 0" class="text-sm text-gray-500 py-4 text-center">لا توجد فواتير قابلة للإرجاع اليوم.</div>
            <div v-else class="space-y-2 max-h-44 overflow-y-auto">
              <button
                v-for="order in refundRecentOrders"
                :key="order.id"
                type="button"
                class="w-full text-right p-3 rounded-lg border transition flex justify-between items-center gap-3"
                :class="selectedRefundOrder?.id === order.id ? 'border-amber-500 bg-amber-50' : 'border-gray-200 hover:bg-gray-50'"
                @click="selectRefundOrder(order)"
              >
                <div>
                  <div class="font-semibold text-gray-800">#{{ order.invoice_number || order.id }}</div>
                  <div class="text-xs text-gray-500">{{ formatRefundDate(order.created_at) }}</div>
                </div>
                <div class="text-left">
                  <div class="font-bold text-green-700">{{ order.total }} جنيه</div>
                  <span v-if="order.is_refunded" class="text-xs text-red-600">مرتجع</span>
                </div>
              </button>
            </div>
          </div>

          <div v-if="refundError" class="rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            {{ refundError }}
          </div>

          <div v-if="selectedRefundOrder" class="rounded-xl border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 flex flex-wrap justify-between gap-2 items-center">
              <div>
                <div class="font-bold text-gray-800">فاتورة #{{ selectedRefundOrder.invoice_number || selectedRefundOrder.id }}</div>
                <div class="text-xs text-gray-500">{{ formatRefundDate(selectedRefundOrder.created_at) }}</div>
              </div>
              <div class="font-bold text-lg text-green-700">{{ selectedRefundOrder.total }} جنيه</div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="p-2 text-right">المنتج</th>
                    <th class="p-2 text-center">الكمية</th>
                    <th class="p-2 text-center">السعر</th>
                    <th class="p-2 text-center">الإجمالي</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(item, idx) in selectedRefundOrder.items" :key="idx" class="border-t">
                    <td class="p-2">
                      {{ item.product_name }}
                      <span v-if="item.from_fridge" class="text-cyan-600 text-xs"> (تلاجة)</span>
                    </td>
                    <td class="p-2 text-center">{{ item.quantity }}</td>
                    <td class="p-2 text-center">{{ item.price }}</td>
                    <td class="p-2 text-center">{{ item.line_total }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="selectedRefundOrder.is_refunded" class="p-4 bg-red-50 text-red-700 text-sm text-center font-medium">
              تم إرجاع هذه الفاتورة مسبقاً
            </div>
          </div>
        </div>

        <div class="p-5 border-t border-gray-200 flex gap-3">
          <button
            type="button"
            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition disabled:bg-gray-400"
            :disabled="refundLoading || !selectedRefundOrder || !selectedRefundOrder.can_refund"
            @click="confirmRefund"
          >
            {{ refundLoading ? 'جاري الإرجاع...' : 'تأكيد المرتجع وإعادة المخزون' }}
          </button>
          <button
            type="button"
            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition"
            @click="closeRefundModal"
          >
            إلغاء
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { applyOffersToCart } from '@/utils/offerMatching';

export default {
  props: {
    products: Array,
    categories: Array,
    fridgeProducts: { type: Array, default: () => [] },
    fridgeSectionEnabled: { type: Boolean, default: false },
    offers: { type: Array, default: () => [] },
  },
  data() {
    return {
      searchQuery: '',
      selectedCategoryId: null,
      showFridgeView: false,
      rawCart: [],
      orderId: null,
      iframeVisible: false,
      liveProducts: [],
      isCheckoutLoading: false,
      pendingClientRequestId: null,
      sizeTranslations: {
        small: 'صغير',
        medium: 'وسط',
        large: 'كبير',
        extra_large: 'كان كبير',
      },
      // متغيرات إدارة الورديات
      currentShift: null,
      showShiftModal: false,
      showCloseShiftModal: false,
      showSalesModal: false,
      showFridgeAvailableModal: false,
      fridgePromptProduct: null,
      fridgePromptEntry: null,
      newShiftType: 'morning',
      isStartingShift: false,

      isClosingShift: false,
      isHandingOver: false,
      closedShift: null,
      cashAmount: 0,
      shiftNotes: '',
      salesDetails: [],
      showRefundModal: false,
      refundSearchQuery: '',
      refundRecentOrders: [],
      refundSearchResults: [],
      selectedRefundOrder: null,
      refundLoading: false,
      refundRecentLoading: false,
      refundError: '',
      refundSearchDebounceTimer: null,
      staffNotes: '',
    };
  },
  computed: {
    usesDualPrinters() {
      return this.$page.props.branchContext?.printerSettings?.mode === 'dual';
    },
    filteredProducts() {
      return this.liveProducts
        .filter(p => this.selectedCategoryId === null || p.category_id === this.selectedCategoryId)
        .filter(p => p.name.toLowerCase().includes(this.searchQuery.toLowerCase()));
    },
    filteredFridgeProducts() {
      return (this.fridgeProducts || [])
        .filter(p => p.name.toLowerCase().includes(this.searchQuery.toLowerCase()))
        .map(p => ({
          ...p,
          from_fridge: true,
          cartKey: `fridge-${p.product_id}-${p.size || ''}`,
          quantityToAdd: 1,
          selectedVariantIndex: -1,
          outOfFridgeStock: parseFloat(p.fridge_quantity) <= 0,
        }));
    },
    displayProducts() {
      return this.showFridgeView ? this.filteredFridgeProducts : this.filteredProducts;
    },
    cartProcessing() {
      const fridgeItems = this.rawCart.filter((i) => i.from_fridge);
      const regularItems = this.rawCart.filter((i) => !i.from_fridge);
      const { applied, remaining } = applyOffersToCart(this.offers, regularItems);
      return { applied, remaining, fridge: fridgeItems };
    },
    cart() {
      const { applied, remaining, fridge } = this.cartProcessing;
      return [...applied, ...remaining, ...fridge];
    },
    totalAmount() {
      return this.cart.reduce((total, item) => total + item.price * item.quantity, 0).toFixed(2);
    },
    totalSavings() {
      return this.cartProcessing.applied.reduce((sum, item) => sum + (item.savings || 0) * item.quantity, 0);
    },
    fridgePromptLabel() {
      const p = this.fridgePromptProduct;
      if (!p) return '';
      let label = p.name;
      const size = this.getProductSizeForCart(p);
      if (size) {
        label += ` (${this.translateSize(size)})`;
      }
      return label;
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
        if (product.from_fridge) {
            return `${product.price} جنيه`;
        }
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
      this.showFridgeView = false;
      this.selectedCategoryId = id;
    },
    selectFridgeView() {
      this.showFridgeView = true;
      this.selectedCategoryId = null;
    },
    getProductSizeForCart(product) {
      if (this.hasVariants(product) && product.selectedVariantIndex !== -1) {
        return product.size_variants[product.selectedVariantIndex]?.size ?? null;
      }
      return null;
    },
    findFridgeStockForProduct(product) {
      if (!this.fridgeProducts?.length) {
        return null;
      }
      const productId = product.id;
      const size = this.getProductSizeForCart(product) ?? '';
      return this.fridgeProducts.find((fp) => {
        if (fp.product_id !== productId) {
          return false;
        }
        const fpSize = fp.size ?? '';
        return fpSize === size;
      }) || null;
    },
    closeFridgeAvailableModal() {
      this.showFridgeAvailableModal = false;
      this.fridgePromptProduct = null;
      this.fridgePromptEntry = null;
    },
    goToFridgeFromPrompt() {
      this.closeFridgeAvailableModal();
      this.selectFridgeView();
    },
    skipFridgePromptAndAdd() {
      const product = this.fridgePromptProduct;
      this.closeFridgeAvailableModal();
      if (product) {
        this.addToCartDirect(product);
      }
    },
    addFridgeToCart(product) {
      const quantity = product.quantityToAdd || 1;
      const cartItemId = `fridge-${product.product_id}-${product.size || ''}`;
      const found = this.rawCart.find(item => item.cartItemId === cartItemId);
      if (found) {
        found.quantity += quantity;
      } else {
        this.rawCart.push({
          cartItemId,
          product_id: product.product_id,
          name: product.name,
          size: product.size,
          price: parseFloat(product.price) || 0,
          quantity,
          from_fridge: true,
        });
      }
      product.quantityToAdd = 1;
    },
    selectVariant(product, variantIndex) {
        product.selectedVariantIndex = variantIndex;
    },
    addToCart(product) {
        if (!this.showFridgeView) {
            const fridgeEntry = this.findFridgeStockForProduct(product);
            if (fridgeEntry && parseFloat(fridgeEntry.fridge_quantity) > 0) {
                this.fridgePromptProduct = product;
                this.fridgePromptEntry = fridgeEntry;
                this.showFridgeAvailableModal = true;
                return;
            }
        }
        this.addToCartDirect(product);
    },
    addToCartDirect(product) {
        const quantity = product.quantityToAdd || 1;

        if (this.hasVariants(product)) {
            const variant = product.size_variants[product.selectedVariantIndex];
            if (!variant) return;
            
            const cartItemId = `${product.id}-${variant.size}`;
            const found = this.rawCart.find(item => item.cartItemId === cartItemId);

            if (found) {
                found.quantity += quantity;
            } else {
                this.rawCart.push({
                    cartItemId: cartItemId,
                    product_id: product.id,
                    category_id: product.category_id,
                    name: product.name,
                    size: variant.size,
                    price: parseFloat(variant.price) || 0,
                    quantity: quantity
                });
            }
        } else {
            const cartItemId = `${product.id}`;
            const found = this.rawCart.find(item => item.cartItemId === cartItemId);

            if (found) {
                found.quantity += quantity;
            } else {
                this.rawCart.push({
                    cartItemId: cartItemId,
                    product_id: product.id,
                    category_id: product.category_id,
                    name: product.name,
                    size: null,
                    price: parseFloat(product.price) || 0,
                    quantity: quantity
                });
            }
        }
      product.quantityToAdd = 1;
    },
    removeFromCart(index) {
      const item = this.cart[index];
      if (!item || item.type === 'offer') return;
      this.rawCart = this.rawCart.filter((r) => r.cartItemId !== item.cartItemId);
    },
    updateQuantity(index, change) {
      const item = this.cart[index];
      if (!item || item.type === 'offer') return;
      const raw = this.rawCart.find((r) => r.cartItemId === item.cartItemId);
      if (!raw) return;
      raw.quantity += change;
      if (raw.quantity <= 0) {
        this.rawCart = this.rawCart.filter((r) => r.cartItemId !== item.cartItemId);
      }
    },
    clearCart() {
      this.rawCart = [];
      this.staffNotes = '';
      this.pendingClientRequestId = null;
    },
    createClientRequestId() {
      if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID().replace(/-/g, '');
      }
      return 'req_' + Date.now() + '_' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    },
    async checkout() {
      // منع الضغط المتكرر أثناء انتظار الرد
      if (this.isCheckoutLoading) {
        console.log('طلب معلق قيد المعالجة...');
        return;
      }

      if (!this.cart.length) {
        return;
      }

      this.isCheckoutLoading = true;

      // معرف ثابت لنفس محاولة البيع — لا يتغير عند إعادة المحاولة بعد ضعف الشبكة
      if (!this.pendingClientRequestId) {
        this.pendingClientRequestId = this.createClientRequestId();
      }
      const clientRequestId = this.pendingClientRequestId;

      const checkoutData = {
        client_request_id: clientRequestId,
        items: this.cart.flatMap((item) => {
          if (item.type === 'offer') {
            return [{
              offer_id: item.offer_id,
              product_id: item.components[0].product_id,
              product_name: item.name,
              quantity: parseInt(item.quantity) || 1,
              price: parseFloat(item.price) || 0,
              size: null,
              from_fridge: false,
              components: item.components,
            }];
          }
          return [{
            product_id: item.product_id,
            product_name: item.name,
            quantity: parseInt(item.quantity) || 0,
            price: parseFloat(item.price) || 0,
            size: item.size,
            from_fridge: !!item.from_fridge,
          }];
        }),
        total_price: parseFloat(this.totalAmount) || 0,
        payment_method: 'cash',
        staff_notes: this.usesDualPrinters && this.staffNotes.trim() ? this.staffNotes.trim() : null,
      };

      try {
        console.log('محاولة إنشاء طلب...', clientRequestId);
        const response = await axios.post('/store-order', checkoutData, {
          timeout: 20000,
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Client-Request-ID': clientRequestId,
          },
        });

        if (response.data.success) {
          this.orderId = response.data.order_id;
          this.pendingClientRequestId = null;
          this.clearCart();
          this.printInvoice();
          if (response.data.idempotent_replay) {
            console.log('تم استرجاع فاتورة محفوظة مسبقاً بنفس المفتاح (منع التكرار).');
          }
        } else if (response.data.processing) {
          alert('الطلب قيد المعالجة على السيرفر. انتظر لحظات ثم اضغط إصدار الفاتورة مرة أخرى إن لم تُطبع.');
        } else {
          alert('فشل في إنشاء الطلب: ' + (response.data.message || ''));
        }
      } catch (error) {
        console.error('خطأ أثناء إصدار الفاتورة:', error);

        // إن وصلت استجابة نجاح/إعادة تشغيل من السيرفر رغم الخطأ الشبكي الظاهر
        if (error.response?.data?.success && error.response?.data?.order_id) {
          this.orderId = error.response.data.order_id;
          this.pendingClientRequestId = null;
          this.clearCart();
          this.printInvoice();
          return;
        }

        if (error.response?.data?.processing) {
          alert('الطلب قيد المعالجة. انتظر قليلاً ثم أعد المحاولة — لن تُنشأ فاتورة مكررة.');
          return;
        }

        const networkIssue = !error.response || error.code === 'ECONNABORTED' || error.message?.includes('timeout');
        if (networkIssue) {
          alert('تعذر تأكيد الحفظ بسبب الشبكة. أعد المحاولة بنفس السلة — النظام يمنع تكرار الفاتورة تلقائياً.');
        } else {
          alert('حدث خطأ: ' + (error.response?.data?.message || 'يرجى مراجعة البيانات'));
        }
      } finally {
        this.isCheckoutLoading = false;
      }
    },










    printInvoice() {
      const settings = this.$page.props.branchContext?.printerSettings;

      if (settings?.method === 'qz' && settings?.customer_printer) {
        this.printInvoiceViaQzTray(settings);
        return;
      }

      this.printInvoiceViaBrowser();
    },
    printInvoiceViaBrowser() {
      this.iframeVisible = true;

      this.$nextTick(() => {
        const iframe = document.getElementById('invoice-frame');
        if (iframe) {
          iframe.onload = () => {
            console.log('تم تحميل الفاتورة - الطباعة ستتم تلقائياً');
          };

          iframe.src = `/invoice-html/${this.orderId}?copy=customer`;
        }
      });
    },
    async printInvoiceViaQzTray(settings) {
      try {
        const { printInvoiceViaQz } = await import('@/utils/qzPrint');
        await printInvoiceViaQz(this.orderId, settings);
      } catch (error) {
        console.error('QZ print failed, falling back to browser:', error);
        alert('فشلت الطباعة عبر QZ Tray. تأكد أن البرنامج يعمل. سيتم استخدام طباعة المتصفح.');
        this.printInvoiceViaBrowser();
      }
    },
    closeIframe() {
      this.iframeVisible = false;
    },
    handleEscape(e) {
      if (e.key === 'Escape') {
        if (this.showRefundModal) {
          this.closeRefundModal();
          return;
        }
        this.closeIframe();
      }
    },
    handleIframeMessage(e) {
      if (e.data === 'close-iframe') {
        this.closeIframe();
      }
    },
    // === إدارة الورديات ===
    
    async openRefundModal() {
      this.showRefundModal = true;
      this.refundError = '';
      this.selectedRefundOrder = null;
      this.refundSearchQuery = '';
      this.refundSearchResults = [];
      await this.loadRefundRecentOrders();
    },
    closeRefundModal() {
      this.clearRefundSearchDebounce();
      this.showRefundModal = false;
      this.refundError = '';
      this.selectedRefundOrder = null;
      this.refundSearchQuery = '';
      this.refundSearchResults = [];
    },
    formatRefundDate(dateString) {
      if (!dateString) return '';
      return new Date(dateString).toLocaleString('ar-EG', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    async loadRefundRecentOrders() {
      this.refundRecentLoading = true;
      try {
        const { data } = await axios.get('/cashier/refunds/recent');
        this.refundRecentOrders = data.orders || [];
      } catch (error) {
        console.error(error);
        this.refundRecentOrders = [];
      } finally {
        this.refundRecentLoading = false;
      }
    },
    selectRefundOrder(order) {
      this.selectedRefundOrder = order;
      this.refundError = '';
    },
    clearRefundSearchDebounce() {
      if (this.refundSearchDebounceTimer) {
        clearTimeout(this.refundSearchDebounceTimer);
        this.refundSearchDebounceTimer = null;
      }
    },
    scheduleRefundSearch() {
      this.clearRefundSearchDebounce();

      const q = this.refundSearchQuery.trim();
      if (!q) {
        this.refundSearchResults = [];
        this.refundError = '';
        return;
      }

      if (q.length < 2) {
        this.refundSearchResults = [];
        this.refundError = '';
        return;
      }

      this.refundSearchDebounceTimer = setTimeout(() => {
        this.searchRefundInvoice();
      }, 400);
    },
    async searchRefundInvoice() {
      const q = this.refundSearchQuery.trim();
      if (!q) return;

      this.refundLoading = true;
      this.refundError = '';
      this.refundSearchResults = [];
      try {
        const { data } = await axios.get('/cashier/refunds/lookup', { params: { q } });
        const orders = data.orders || (data.order ? [data.order] : []);
        this.refundSearchResults = orders;

        if (orders.length === 1) {
          this.selectedRefundOrder = orders[0];
        } else if (orders.length > 1) {
          this.selectedRefundOrder = null;
        }
      } catch (error) {
        this.selectedRefundOrder = null;
        this.refundSearchResults = [];
        this.refundError = error.response?.data?.message || 'لم يتم العثور على الفاتورة في فواتير اليوم.';
      } finally {
        this.refundLoading = false;
      }
    },
    async confirmRefund() {
      if (!this.selectedRefundOrder?.can_refund) return;

      const label = this.selectedRefundOrder.invoice_number || this.selectedRefundOrder.id;
      if (!confirm(`تأكيد مرتجع الفاتورة #${label}؟\n\nسيتم إعادة المكونات والمخزون تلقائياً.`)) {
        return;
      }

      this.refundLoading = true;
      this.refundError = '';
      try {
        const { data } = await axios.post(`/cashier/orders/${this.selectedRefundOrder.id}/refund`);
        this.selectedRefundOrder = data.order;
        this.refundSearchResults = [];
        await this.loadRefundRecentOrders();
        alert(data.message || 'تم الإرجاع بنجاح');
      } catch (error) {
        this.refundError = error.response?.data?.message || 'فشل إرجاع الفاتورة.';
      } finally {
        this.refundLoading = false;
      }
    },

    // === إدارة الورديات (تابع) ===
    
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




  },
  mounted() {
    this.initializeProducts();
    document.addEventListener('keydown', this.handleEscape);
    window.addEventListener('message', this.handleIframeMessage);
    
    // الحصول على الوردية الحالية
    this.getCurrentShift();
    

    

  },
  beforeDestroy() {
    document.removeEventListener('keydown', this.handleEscape);
    window.removeEventListener('message', this.handleIframeMessage);
    this.clearRefundSearchDebounce();
  },
  watch: {
      products() {
          this.initializeProducts();
      },
      refundSearchQuery() {
          if (this.showRefundModal) {
              this.scheduleRefundSearch();
          }
      },
  }
};
</script>

