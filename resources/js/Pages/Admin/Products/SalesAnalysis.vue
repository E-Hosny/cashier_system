<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">📈 تحليل مبيعات المنتجات</h1>
      <a :href="route('admin.products.index')" class="btn-gray text-center">➡️ العودة إلى المنتجات</a>
    </div>

    <!-- بطاقة الفلاتر -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
      <div class="flex flex-col md:flex-row gap-4 items-end flex-wrap">
        <div class="flex flex-col gap-1">
          <label class="text-gray-700 font-semibold">نوع التجميع:</label>
          <select v-model="groupBy" class="p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 min-w-[180px]">
            <option value="category">حسب الفئة</option>
            <option value="product">حسب المنتجات</option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-gray-700 font-semibold">من تاريخ:</label>
          <input type="date" v-model="dateFrom" class="p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-gray-700 font-semibold">إلى تاريخ:</label>
          <input type="date" v-model="dateTo" class="p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300" />
        </div>
        <button @click="applyFilters" class="btn-primary py-3 px-5">تطبيق</button>
      </div>
      <p class="text-sm text-gray-500 mt-3">
        ترك تواريخ فارغة = كل الأوقات حتى الآن. المبيعات تُحسب من الساعة 7:00 صباحاً إلى 7:00 صباحاً من اليوم التالي.
      </p>
    </div>

    <!-- ملخص سريع -->
    <div v-if="analysis.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
      <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-blue-500">
        <div class="text-gray-600 font-medium mb-1">إجمالي الوحدات المباعة</div>
        <div class="text-2xl font-bold text-blue-600">{{ total_quantity.toLocaleString('ar-EG') }}</div>
      </div>
      <div class="bg-white rounded-xl shadow-lg p-6 border-r-4 border-green-500">
        <div class="text-gray-600 font-medium mb-1">إجمالي المبيعات</div>
        <div class="text-2xl font-bold text-green-600">{{ formatPrice(total_revenue) }} جنيه</div>
      </div>
    </div>

    <!-- جدول النتائج -->
    <div class="bg-white shadow-lg rounded-xl overflow-x-auto">
      <table class="w-full text-end">
        <thead class="bg-gray-200">
          <tr>
            <th class="p-4">#</th>
            <th class="p-4">{{ group_by === 'category' ? 'الفئة' : 'المنتج' }}</th>
            <th class="p-4">عدد الوحدات المباعة</th>
            <th class="p-4">النسبة المئوية (كمية)</th>
            <th class="p-4">إجمالي المبلغ</th>
            <th class="p-4">النسبة المئوية (مبيعات)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="analysis.length === 0">
            <td colspan="6" class="text-center p-8 text-gray-500">
              لا توجد بيانات مبيعات للفترة أو التجميع المحدد.
            </td>
          </tr>
          <tr
            v-for="(row, index) in analysis"
            :key="row.name + index"
            class="border-t border-gray-200 hover:bg-gray-50"
          >
            <td class="p-4 font-medium text-gray-600">{{ index + 1 }}</td>
            <td class="p-4 font-semibold text-gray-800">{{ row.name }}</td>
            <td class="p-4 font-bold text-blue-600">{{ row.total_quantity.toLocaleString('ar-EG') }}</td>
            <td class="p-4">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-6 min-w-[80px] overflow-hidden">
                  <div
                    class="h-full bg-blue-500 rounded-full transition-all"
                    :style="{ width: Math.min(row.quantity_percentage, 100) + '%' }"
                  />
                </div>
                <span class="font-bold text-gray-700 whitespace-nowrap">{{ row.quantity_percentage }}%</span>
              </div>
            </td>
            <td class="p-4 font-bold text-green-600">{{ formatPrice(row.total_revenue) }} جنيه</td>
            <td class="p-4">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-6 min-w-[80px] overflow-hidden">
                  <div
                    class="h-full bg-green-500 rounded-full transition-all"
                    :style="{ width: Math.min(row.revenue_percentage, 100) + '%' }"
                  />
                </div>
                <span class="font-bold text-gray-700 whitespace-nowrap">{{ row.revenue_percentage }}%</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Inertia } from '@inertiajs/inertia';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    analysis: Array,
    group_by: String,
    date_from: String,
    date_to: String,
    total_quantity: Number,
    total_revenue: Number,
    categories: Array,
  },
  data() {
    return {
      groupBy: this.group_by || 'category',
      dateFrom: this.date_from || '',
      dateTo: this.date_to || '',
    };
  },
  watch: {
    group_by(val) {
      this.groupBy = val || 'category';
    },
    date_from(val) {
      this.dateFrom = val || '';
    },
    date_to(val) {
      this.dateTo = val || '';
    },
  },
  methods: {
    applyFilters() {
      const params = { group_by: this.groupBy };
      if (this.dateFrom) params.date_from = this.dateFrom;
      if (this.dateTo) params.date_to = this.dateTo;
      Inertia.get(route('admin.products.sales-analysis'), params);
    },
    formatPrice(price) {
      return price != null ? Number(price).toFixed(2) : '0.00';
    },
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style>
