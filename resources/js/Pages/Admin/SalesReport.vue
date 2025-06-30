<template>
  <AppLayout title="تقرير المبيعات">
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            📊 تقرير المبيعات
        </h2>
    </template>
    <div class="py-12" dir="rtl">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- اختيار تاريخ -->
          <div class="mb-6 flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-4">
            <label class="text-gray-700 font-medium">📅 اختر التاريخ:</label>
            <input type="date" v-model="selectedDate" @change="fetchSales" class="p-2 border rounded-lg" />
          </div>

          <!-- جدول المبيعات -->
          <div class="overflow-x-auto">
            <table class="w-full bg-white rounded-lg text-end responsive-table">
              <thead class="bg-gray-100">
                <tr class="text-gray-700 text-end">
                  <th class="p-4">📦 المنتج</th>
                  <th class="p-4">📊 الكمية المباعة</th>
                  <th class="p-4">💰 سعر الوحدة</th>
                  <th class="p-4">💵 إجمالي المبيعات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="sales.length === 0">
                    <td colspan="4" class="text-center p-6 text-gray-500">
                        لا توجد بيانات مبيعات لهذا اليوم.
                    </td>
                </tr>
                <tr v-for="sale in sales" :key="sale.product_id" class="border-t text-end">
                  <td class="p-4 font-semibold" data-label="المنتج">{{ sale.product.name }}</td>
                  <td class="p-4 text-blue-600 font-bold" data-label="الكمية المباعة">{{ sale.total_quantity }}</td>
                  <td class="p-4 text-green-600 font-bold" data-label="سعر الوحدة">{{ formatPrice(sale.unit_price) }}</td>
                  <td class="p-4 text-red-600 font-bold" data-label="إجمالي المبيعات">{{ formatPrice(sale.total_price) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- إجمالي المبيعات -->
          <div v-if="sales.length > 0" class="mt-6 text-xl font-bold text-center bg-gray-200 p-4 rounded-lg">
            💵 إجمالي المبيعات: {{ formatPrice(totalSales) }}
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
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

<style>
/* Styles for responsive table */
@media (max-width: 640px) {
    .responsive-table thead {
        display: none;
    }
    .responsive-table tbody,
    .responsive-table tr,
    .responsive-table td {
        display: block;
        width: 100%;
    }
    .responsive-table tr {
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .responsive-table td {
        padding: 0.75rem 1rem;
        position: relative;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .responsive-table td:last-child {
        border-bottom: none;
    }
    .responsive-table td[data-label]::before {
        content: attr(data-label) ":";
        font-weight: bold;
        text-align: right;
        margin-left: 0.5rem;
    }
}
</style>
