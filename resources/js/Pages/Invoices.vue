<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  orders: Array,
  start: String,
  end: String,
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
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">فواتير اليوم (من {{ formatDate(start) }} إلى {{ formatDate(end) }})</h2>
    </template>
    <div class="py-8 max-w-5xl mx-auto">
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
    </div>
  </AppLayout>
</template> 