<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  orders: Array,
  start: String,
  end: String,
  selectedDate: String,
});

// تحديد تاريخ اليوم الحالي تلقائياً
const getCurrentDate = () => {
  const today = new Date();
  return today.toISOString().split('T')[0];
};

const selectedDate = ref(props.selectedDate || getCurrentDate());

const onDateChange = () => {
  router.get('/invoices', { date: selectedDate.value }, { preserveState: true });
};

const clearDate = () => {
  selectedDate.value = getCurrentDate();
  router.get('/invoices', {}, { preserveState: true });
};

// حساب إجمالي مبلغ الفواتير
const totalAmount = computed(() => {
  return props.orders.reduce((sum, order) => sum + parseFloat(order.total), 0);
});

const formatDate = (date) => {
  return new Date(date).toLocaleString('ar-EG', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hour12: false
  });
};

const translateSize = (size) => {
  if (!size) return '-';
  switch (size) {
    case 'small':
    case 'صغير':
      return 'صغير';
    case 'medium':
    case 'وسط':
      return 'وسط';
    case 'large':
    case 'كبير':
      return 'كبير';
    default:
      return size;
  }
};
</script>

<template>
  <AppLayout title="فواتير اليوم">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ selectedDate ? 'فواتير يوم محدد' : 'فواتير اليوم' }} 
        (من {{ formatDate(start) }} إلى {{ formatDate(end) }})
      </h2>
    </template>
    <div class="py-8 max-w-5xl mx-auto">
      <!-- Date Filter Section -->
      <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">تصفية الفواتير حسب التاريخ</h3>
        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
          <div class="flex flex-col">
            <label for="date-filter" class="text-sm font-medium text-gray-700 mb-2">
              اختر التاريخ (اليوم يبدأ من 7 صباحاً إلى 7 صباحاً لليوم التالي):
            </label>
            <input
              id="date-filter"
              type="date"
              v-model="selectedDate"
              @change="onDateChange"
              class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
            />
          </div>
          <div class="flex gap-2 mt-2 sm:mt-6">
            <button
              @click="onDateChange"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-200"
            >
              تطبيق
            </button>
            <button
              @click="clearDate"
              class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 transition duration-200"
            >
              إعادة تعيين
            </button>
          </div>
        </div>
        <div v-if="selectedDate" class="mt-3 text-sm text-gray-600">
          <span class="font-medium">التاريخ المحدد:</span> 
          {{ new Date(selectedDate).toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' }) }}
        </div>
      </div>
      <div v-if="orders.length === 0" class="text-center text-gray-500 py-12 text-lg">لا توجد فواتير في هذه الفترة.</div>
      <div v-for="order in orders" :key="order.id" class="mb-8 bg-white rounded-lg shadow p-6 border border-gray-200">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
          <div class="font-bold text-lg text-indigo-700">فاتورة رقم: {{ order.invoice_number || order.id }}</div>
          <div class="text-gray-600 text-sm">التاريخ: {{ formatDate(order.created_at) }}</div>
          <div class="text-green-700 font-bold text-lg">الإجمالي: {{ order.total }} جنيه</div>
        </div>
        <div class="overflow-x-auto" dir="rtl">
          <table class="min-w-full text-sm border">
            <thead class="bg-gray-100">
              <tr>
                <th class="p-2 border text-center">#</th>
                <th class="p-2 border text-center">المنتج</th>
                <th class="p-2 border text-center">الكمية</th>
                <th class="p-2 border text-center">السعر</th>
                <th class="p-2 border text-center">الحجم</th>
                <th class="p-2 border text-center">الإجمالي</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in order.items" :key="idx">
                <td class="p-2 border text-center">{{ idx + 1 }}</td>
                <td class="p-2 border text-center">{{ item.product_name }}</td>
                <td class="p-2 border text-center">{{ item.quantity }}</td>
                <td class="p-2 border text-center">{{ item.price }}</td>
                <td class="p-2 border text-center">{{ translateSize(item.size) }}</td>
                <td class="p-2 border text-center">{{ (item.price * item.quantity).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- إجمالي مبلغ الفواتير -->
      <div v-if="orders.length > 0" class="mt-8 bg-gradient-to-r from-green-50 to-green-100 rounded-lg shadow-lg p-6 border-2 border-green-200">
        <div class="text-center">
          <h3 class="text-xl font-bold text-green-800 mb-2">إجمالي مبلغ الفواتير</h3>
          <div class="text-3xl font-bold text-green-900">
            {{ totalAmount.toFixed(2) }} جنيه
          </div>
          <div class="text-sm text-green-700 mt-2">
            عدد الفواتير: {{ orders.length }} فاتورة
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template> 