<template>
  <div class="container mx-auto p-6" dir="rtl">
    <h1 class="text-3xl font-extrabold mb-6 text-end text-gray-800">📊 تقرير المبيعات</h1>

    <!-- اختيار تاريخ -->
    <div class="mb-6 flex items-center space-x-4 justify-end">
      <label class="text-gray-700 font-medium">📅 اختر التاريخ:</label>
      <input type="date" v-model="selectedDate" @change="fetchSales" class="p-2 border rounded-lg" />
    </div>

    <!-- جدول المبيعات -->
    <table class="w-full bg-white rounded-lg shadow-lg">
      <thead class="bg-gray-100">
        <tr class="text-gray-700 text-end">
          <th class="p-4">📦 المنتج</th>
          <th class="p-4">📊 الكمية المباعة</th>
          <th class="p-4">💰 سعر الوحدة</th>
          <th class="p-4">💵 إجمالي المبيعات</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="sale in sales" :key="sale.product_id" class="border-t text-end">
          <td class="p-4 font-semibold">{{ sale.product.name }}</td>
          <td class="p-4 text-blue-600 font-bold">{{ sale.total_quantity }}</td>
          <td class="p-4 text-green-600 font-bold">${{ formatPrice(sale.unit_price) }}</td>
          <td class="p-4 text-red-600 font-bold">${{ formatPrice(sale.total_price) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- إجمالي المبيعات -->
    <div class="mt-6 text-xl font-bold text-center bg-gray-200 p-4 rounded-lg">
      💵 إجمالي المبيعات: ${{ formatPrice(totalSales) }}
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";

export default {
  props: {
    sales: Array,
    date: String,
    totalSales: Number,
  },
  data() {
    return {
      selectedDate: this.date, // تعيين التاريخ الافتراضي
    };
  },
  methods: {
    fetchSales() {
      Inertia.get(route("admin.sales.report"), { date: this.selectedDate });
    },
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    }
  },
};
</script>
