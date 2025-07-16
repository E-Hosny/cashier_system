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
          <!-- اختيار فترة التواريخ والتصفية -->
          <div class="mb-6">
            <!-- صف التواريخ -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center sm:justify-end gap-4 mb-4">
              <div class="flex flex-col gap-1">
                <label class="text-gray-700 font-medium">📅 من (يوم أو بداية فترة):</label>
                <input type="date" v-model="dateFrom" class="p-2 border rounded-lg" />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-gray-700 font-medium">إلى (نهاية الفترة - اختياري):</label>
                <input type="date" v-model="dateTo" class="p-2 border rounded-lg" />
              </div>
              <button @click="fetchSales" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg mt-6 sm:mt-0">بحث</button>
            </div>
            
            <!-- صف التصفية -->
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4">
              <div class="flex flex-col gap-1">
                <label class="text-gray-700 font-medium">📂 الفئة (اختياري):</label>
                <select v-model="selectedCategoryId" @change="onCategoryChange" class="p-2 border rounded-lg">
                  <option value="">جميع الفئات</option>
                  <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                  </option>
                </select>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-gray-700 font-medium">📦 المنتج (اختياري):</label>
                <select v-model="selectedProductId" class="p-2 border rounded-lg">
                  <option value="">جميع المنتجات</option>
                  <option v-for="product in filteredProducts" :key="product.id" :value="product.id">
                    {{ product.name }}
                  </option>
                </select>
              </div>
              <button @click="clearFilters" class="bg-gray-500 hover:bg-gray-600 text-white font-bold px-4 py-2 rounded-lg mt-6 sm:mt-0">مسح الفلاتر</button>
            </div>
          </div>
          
          <div class="mb-2 text-sm text-gray-500 text-end">
            يمكنك اختيار يوم واحد فقط أو تحديد فترة من - إلى، مع إمكانية تصفية النتائج حسب الفئة أو المنتج.
          </div>

          <!-- جدول المبيعات -->
          <div class="overflow-x-auto">
            <table class="w-full bg-white rounded-lg text-end responsive-table">
              <thead class="bg-gray-100">
                <tr class="text-gray-700 text-end">
                  <th class="p-4">المنتج</th>
                  <th class="p-4">الفئة</th>
                  <th class="p-4">الحجم</th>
                  <th class="p-4">الكمية</th>
                  <th class="p-4">سعر الوحدة</th>
                  <th class="p-4">إجمالي المبيعات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="sales.length === 0">
                    <td colspan="6" class="text-center p-6 text-gray-500">
                        لا توجد بيانات مبيعات للفترة المحددة.
                    </td>
                </tr>
                <tr v-for="sale in sales" :key="sale.product_id + '-' + (sale.size || 'no-size')" class="border-t text-end">
                  <td class="p-4 font-semibold" data-label="المنتج">{{ sale.product.name }}</td>
                  <td class="p-4 text-gray-600" data-label="الفئة">{{ sale.product.category?.name || 'غير محدد' }}</td>
                  <td class="p-4" data-label="الحجم">{{ sizeToArabic(sale.size) }}</td>
                  <td class="p-4 text-blue-600 font-bold" data-label="الكمية">{{ sale.total_quantity }}</td>
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
          
          <!-- خانة المشتريات معلقة مؤقتاً -->
          <!-- <div v-if="sales.length > 0" class="mt-2 text-lg font-bold text-center bg-gray-100 p-3 rounded-lg">
            🛒 إجمالي المشتريات: {{ formatPrice(totalPurchases) }}
          </div> -->
          
          <!-- إجمالي المصروفات مع رابط -->
          <div v-if="sales.length > 0" class="mt-2 text-lg font-bold text-center bg-gray-100 p-3 rounded-lg cursor-pointer hover:bg-gray-200 transition-colors" @click="goToExpenses">
            💸 إجمالي المصروفات: {{ formatPrice(totalExpenses) }}
            <span class="text-sm text-blue-600 block mt-1">
              اضغط هنا لعرض تفاصيل المصروفات 
              <span v-if="getSelectedDateText()" class="text-gray-600">
                ({{ getSelectedDateText() }})
              </span>
            </span>
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
    date_from: String,
    date_to: String,
    category_id: String,
    product_id: String,
    totalSales: Number,
    totalPurchases: Number,
    totalExpenses: Number,
    categories: Array,
    products: Array,
  },
  data() {
    return {
      dateFrom: this.date_from || this.date, // تعيين التاريخ الافتراضي
      dateTo: this.date_to || '', // اجعل النهاية فارغة افتراضيًا
      selectedCategoryId: this.category_id || '',
      selectedProductId: this.product_id || '',
    };
  },
  computed: {
    filteredProducts() {
      if (!this.selectedCategoryId) {
        return this.products;
      }
      return this.products.filter(product => product.category_id == this.selectedCategoryId);
    }
  },
  methods: {
    fetchSales() {
      const params = { 
        date_from: this.dateFrom,
        category_id: this.selectedCategoryId,
        product_id: this.selectedProductId
      };
      if (this.dateTo) params.date_to = this.dateTo;
      Inertia.get(route("admin.sales.report"), params);
    },
    onCategoryChange() {
      // إعادة تعيين المنتج المحدد عند تغيير الفئة
      this.selectedProductId = '';
      this.fetchSales();
    },
    clearFilters() {
      this.selectedCategoryId = '';
      this.selectedProductId = '';
      this.fetchSales();
    },
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    },
    sizeToArabic(size) {
      if (!size) return 'غير محدد';
      const map = { small: 'صغير', medium: 'وسط', large: 'كبير' };
      return map[size] || size;
    },
    // دالة الانتقال لصفحة المصروفات مع التاريخ المحدد
    goToExpenses() {
      let expenseParams = {};
      
      // تحديد نوع التاريخ المحدد
      if (this.dateFrom && !this.dateTo) {
        // إذا تم تحديد يوم واحد فقط
        expenseParams = {
          expense_date: this.dateFrom
        };
      } else if (this.dateFrom && this.dateTo) {
        // إذا تم تحديد فترة من-إلى
        expenseParams = {
          from: this.dateFrom,
          to: this.dateTo
        };
      } else {
        // افتراضياً: اليوم الحالي
        expenseParams = {
          expense_date: new Date().toISOString().slice(0, 10)
        };
      }
      
      Inertia.get(route('expenses.index'), expenseParams);
    },
    // دالة لعرض نص التاريخ المحدد
    getSelectedDateText() {
      if (this.dateFrom && !this.dateTo) {
        // يوم واحد
        return this.formatDateForDisplay(this.dateFrom);
      } else if (this.dateFrom && this.dateTo) {
        // فترة
        return `من ${this.formatDateForDisplay(this.dateFrom)} إلى ${this.formatDateForDisplay(this.dateTo)}`;
      } else {
        // اليوم الحالي
        return this.formatDateForDisplay(new Date().toISOString().slice(0, 10));
      }
    },
    // دالة تنسيق التاريخ للعرض
    formatDateForDisplay(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
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
