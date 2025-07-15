<template>
  <div class="min-h-screen bg-gray-100" dir="rtl">
    <div class="py-6">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
          <div class="flex justify-between items-center">
            <div>
              <h1 class="text-2xl font-bold text-gray-900">إدارة الطلبات في وضع عدم الاتصال</h1>
              <p class="text-gray-600 mt-1">إدارة الطلبات المحفوظة عند انقطاع الإنترنت</p>
            </div>
            <div class="flex items-center gap-4">
              <!-- مؤشر حالة الاتصال -->
              <div class="flex items-center gap-2">
                <div :class="[
                  'w-3 h-3 rounded-full',
                  isOnline ? 'bg-green-500' : 'bg-red-500'
                ]"></div>
                <span class="text-sm font-medium">
                  {{ isOnline ? 'متصل بالإنترنت' : 'غير متصل' }}
                </span>
              </div>
              
              <!-- زر المزامنة -->
              <button
                @click="syncOrders"
                :disabled="!isOnline || isSyncing"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                <span v-if="isSyncing">جاري المزامنة...</span>
                <span v-else>مزامنة الطلبات</span>
              </button>
            </div>
          </div>
        </div>

        <!-- الإحصائيات -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
              </div>
              <div class="mr-4">
                <p class="text-sm font-medium text-gray-600">إجمالي الطلبات</p>
                <p class="text-2xl font-bold text-gray-900">{{ stats.total || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="mr-4">
                <p class="text-sm font-medium text-gray-600">في انتظار المزامنة</p>
                <p class="text-2xl font-bold text-gray-900">{{ stats.pending || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                </div>
              </div>
              <div class="mr-4">
                <p class="text-sm font-medium text-gray-600">تمت المزامنة</p>
                <p class="text-2xl font-bold text-gray-900">{{ stats.synced || 0 }}</p>
              </div>
            </div>
          </div>

          <div class="bg-white shadow-sm rounded-lg p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                  </svg>
                </div>
              </div>
              <div class="mr-4">
                <p class="text-sm font-medium text-gray-600">فشلت المزامنة</p>
                <p class="text-2xl font-bold text-gray-900">{{ stats.failed || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- الطلبات المعلقة -->
        <div class="bg-white shadow-sm rounded-lg mb-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h2 class="text-lg font-semibold text-gray-900">الطلبات في انتظار المزامنة</h2>
              <span class="text-sm text-gray-500">{{ pendingOrders.length }} طلب</span>
            </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">رقم الطلب</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">رقم الفاتورة</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المبلغ</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">طريقة الدفع</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="order in pendingOrders" :key="order.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ order.offline_id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ order.invoice_number }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ order.total }} ريال
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ order.payment_method }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(order.created_at) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      @click="viewOrder(order)"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      عرض التفاصيل
                    </button>
                  </td>
                </tr>
                <tr v-if="pendingOrders.length === 0">
                  <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
                    لا توجد طلبات في انتظار المزامنة
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- الطلبات الفاشلة -->
        <div class="bg-white shadow-sm rounded-lg mb-6" v-if="failedOrders.length > 0">
          <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
              <h2 class="text-lg font-semibold text-gray-900">الطلبات الفاشلة في المزامنة</h2>
              <button
                @click="retryFailedOrders"
                :disabled="!isOnline || isRetrying"
                class="bg-orange-600 hover:bg-orange-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                <span v-if="isRetrying">جاري المحاولة...</span>
                <span v-else>إعادة المحاولة</span>
              </button>
            </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">رقم الطلب</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">المبلغ</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التاريخ</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">سبب الفشل</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="order in failedOrders" :key="order.id">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ order.offline_id }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    {{ order.total }} ريال
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    {{ formatDate(order.created_at) }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-500">
                    <div class="max-w-xs truncate" :title="order.sync_error">
                      {{ order.sync_error }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button
                      @click="viewOrder(order)"
                      class="text-blue-600 hover:text-blue-900"
                    >
                      عرض التفاصيل
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- أزرار إضافية -->
        <div class="bg-white shadow-sm rounded-lg p-6">
          <div class="flex justify-between items-center">
            <div class="flex gap-4">
              <button
                @click="loadOfflineData"
                :disabled="isLoadingData"
                class="bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                <span v-if="isLoadingData">جاري التحميل...</span>
                <span v-else>تحميل البيانات للعمل في وضع عدم الاتصال</span>
              </button>
              
              <button
                @click="cleanupSyncedOrders"
                :disabled="isCleaning"
                class="bg-gray-600 hover:bg-gray-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
              >
                <span v-if="isCleaning">جاري التنظيف...</span>
                <span v-else>تنظيف الطلبات المزامنة</span>
              </button>
            </div>
            
            <button
              @click="exportOrders"
              class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition"
            >
              تصدير الطلبات
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal عرض تفاصيل الطلب -->
    <div v-if="showOrderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">تفاصيل الطلب</h3>
            <button @click="showOrderModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div v-if="selectedOrder" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">رقم الطلب</label>
                <p class="mt-1 text-sm text-gray-900">{{ selectedOrder.offline_id }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">رقم الفاتورة</label>
                <p class="mt-1 text-sm text-gray-900">{{ selectedOrder.invoice_number }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">المبلغ الإجمالي</label>
                <p class="mt-1 text-sm text-gray-900">{{ selectedOrder.total }} ريال</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">طريقة الدفع</label>
                <p class="mt-1 text-sm text-gray-900">{{ selectedOrder.payment_method }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">التاريخ</label>
                <p class="mt-1 text-sm text-gray-900">{{ formatDate(selectedOrder.created_at) }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700">الحالة</label>
                <span :class="[
                  'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                  selectedOrder.status === 'pending_sync' ? 'bg-yellow-100 text-yellow-800' : '',
                  selectedOrder.status === 'synced' ? 'bg-green-100 text-green-800' : '',
                  selectedOrder.status === 'failed' ? 'bg-red-100 text-red-800' : ''
                ]">
                  {{ getStatusText(selectedOrder.status) }}
                </span>
              </div>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">عناصر الطلب</label>
              <div class="bg-gray-50 rounded-lg p-4">
                <div v-for="item in selectedOrder.items" :key="item.product_id" class="flex justify-between items-center py-2 border-b border-gray-200 last:border-b-0">
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ item.product_name }}</p>
                    <p class="text-xs text-gray-500">{{ item.quantity }} × {{ item.price }} ريال</p>
                  </div>
                  <p class="text-sm font-medium text-gray-900">{{ (item.quantity * item.price).toFixed(2) }} ريال</p>
                </div>
              </div>
            </div>
            
            <div v-if="selectedOrder.sync_error">
              <label class="block text-sm font-medium text-gray-700">سبب الفشل</label>
              <p class="mt-1 text-sm text-red-600">{{ selectedOrder.sync_error }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  props: {
    stats: Object,
    pendingOrders: Array,
    failedOrders: Array,
    isOnline: Boolean,
  },
  data() {
    return {
      isSyncing: false,
      isRetrying: false,
      isLoadingData: false,
      isCleaning: false,
      showOrderModal: false,
      selectedOrder: null,
    };
  },
  methods: {
    async syncOrders() {
      this.isSyncing = true;
      try {
        const response = await axios.post('/offline/sync');
        if (response.data.success) {
          alert(response.data.message);
          this.$inertia.reload();
        } else {
          alert('فشلت المزامنة: ' + response.data.message);
        }
      } catch (error) {
        console.error('خطأ في المزامنة:', error);
        alert('حدث خطأ في المزامنة');
      } finally {
        this.isSyncing = false;
      }
    },

    async retryFailedOrders() {
      this.isRetrying = true;
      try {
        const response = await axios.post('/offline/retry');
        if (response.data.success) {
          alert(response.data.message);
          this.$inertia.reload();
        } else {
          alert('فشلت إعادة المحاولة: ' + response.data.message);
        }
      } catch (error) {
        console.error('خطأ في إعادة المحاولة:', error);
        alert('حدث خطأ في إعادة المحاولة');
      } finally {
        this.isRetrying = false;
      }
    },

    async loadOfflineData() {
      this.isLoadingData = true;
      try {
        const response = await axios.post('/offline/load-data');
        if (response.data.success) {
          alert('تم تحميل البيانات بنجاح للعمل في وضع عدم الاتصال');
        } else {
          alert('فشل تحميل البيانات: ' + response.data.message);
        }
      } catch (error) {
        console.error('خطأ في تحميل البيانات:', error);
        alert('حدث خطأ في تحميل البيانات');
      } finally {
        this.isLoadingData = false;
      }
    },

    async cleanupSyncedOrders() {
      if (!confirm('هل أنت متأكد من حذف الطلبات المزامنة بنجاح؟')) {
        return;
      }

      this.isCleaning = true;
      try {
        const response = await axios.post('/offline/cleanup');
        if (response.data.success) {
          alert(response.data.message);
          this.$inertia.reload();
        } else {
          alert('فشل التنظيف: ' + response.data.message);
        }
      } catch (error) {
        console.error('خطأ في التنظيف:', error);
        alert('حدث خطأ في التنظيف');
      } finally {
        this.isCleaning = false;
      }
    },



    async exportOrders() {
      try {
        const response = await axios.get('/offline/export');
        if (response.data.success) {
          // تحميل الملف
          const dataStr = JSON.stringify(response.data, null, 2);
          const dataBlob = new Blob([dataStr], { type: 'application/json' });
          const url = URL.createObjectURL(dataBlob);
          const link = document.createElement('a');
          link.href = url;
          link.download = `offline_orders_${new Date().toISOString().split('T')[0]}.json`;
          link.click();
          URL.revokeObjectURL(url);
        } else {
          alert('فشل تصدير الطلبات: ' + response.data.message);
        }
      } catch (error) {
        console.error('خطأ في تصدير الطلبات:', error);
        alert('حدث خطأ في تصدير الطلبات');
      }
    },

    viewOrder(order) {
      this.selectedOrder = order;
      this.showOrderModal = true;
    },

    formatDate(dateString) {
      return new Date(dateString).toLocaleString('ar-SA');
    },

    getStatusText(status) {
      const statusMap = {
        'pending_sync': 'في انتظار المزامنة',
        'synced': 'تمت المزامنة',
        'failed': 'فشلت المزامنة'
      };
      return statusMap[status] || status;
    },
  },
};
</script> 