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
            <!-- فرع التقرير (محور السوبر أدمن فقط) -->
            <div v-if="salesReportHub" class="flex flex-col gap-1 mb-4 max-w-md ms-auto">
              <label class="text-gray-700 font-medium text-end">🏷 تقرير حسب الفرع:</label>
              <select
                v-model="selectedReportBranchId"
                class="p-2 border rounded-lg text-end"
                @change="fetchSales"
              >
                <option value="">جميع الفروع (إجمالي مركزي + توزيع)</option>
                <option v-for="b in reportBranches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
              </select>
              <p v-if="selectedReportBranchId" class="text-sm text-gray-600 text-end">
                يعرض المبيعات والمصروفات والرواتب لهذا الفرع فقط؛ جداول التوزيع حسب الفرع مخفية.
              </p>
            </div>
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
            <span class="text-blue-600 font-medium">⚠️ ملاحظة: المبيعات تُحسب من الساعة 7:00 صباحاً إلى الساعة 7:00 صباحاً من اليوم التالي</span>
            <br>
            <span class="text-green-600 font-medium">🕐 تلقائي: إذا دخلت قبل الساعة 7 صباحاً، ستظهر مبيعات اليوم السابق. إذا دخلت بعد الساعة 7 صباحاً، ستظهر مبيعات اليوم الحالي.</span>
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

          <!-- ملخص الإجماليات -->
          <div v-if="sales.length > 0" class="mt-6 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="text-lg font-bold text-center bg-gray-200 p-4 rounded-lg">
              <div class="text-gray-800 text-sm mb-1">💵 إجمالي المبيعات</div>
              <div class="text-xl">{{ formatPrice(totalSales) }}</div>
            </div>

            <div
              class="text-lg font-bold text-center bg-gray-100 p-4 rounded-lg cursor-pointer hover:bg-gray-200 transition-colors"
              @click="goToExpenses"
            >
              <div class="text-gray-800 text-sm mb-1">💸 إجمالي المصروفات</div>
              <div class="text-xl text-red-700">{{ formatPrice(totalExpenses) }}</div>
              <span class="text-xs text-blue-600 block mt-2">عرض التفاصيل</span>
            </div>

            <div
              class="text-lg font-bold text-center bg-orange-100 p-4 rounded-lg cursor-pointer hover:bg-orange-200 transition-colors"
              @click="goToEmployees"
            >
              <div class="text-gray-800 text-sm mb-1">👥 إجمالي الرواتب المسلمة</div>
              <div class="text-xl text-orange-700">{{ formatPrice(totalSalaries) }}</div>
              <span class="text-xs text-blue-600 block mt-2">عرض التفاصيل</span>
            </div>

            <div class="text-lg font-bold text-center bg-indigo-100 p-4 rounded-lg">
              <div class="text-gray-800 text-sm mb-1">📊 الصافي</div>
              <div class="text-xl" :class="netTotal >= 0 ? 'text-indigo-700' : 'text-red-700'">
                {{ formatPrice(netTotal) }}
              </div>
              <span class="text-xs text-gray-600 block mt-2">المبيعات − المصروفات − الرواتب</span>
            </div>
          </div>

          <p v-if="sales.length > 0 && getSelectedDateText()" class="mt-2 text-sm text-gray-500 text-center">
            الفترة: {{ getSelectedDateText() }}
          </p>

          <div v-if="aggregateAllBranches && branchSalesSummary && branchSalesSummary.length" class="mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3 text-end">توزيع المبيعات حسب الفرع</h3>
            <div class="overflow-x-auto rounded-lg border">
              <table class="w-full bg-white text-end">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="p-3">الفرع</th>
                    <th class="p-3">إجمالي المبيعات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in branchSalesSummary" :key="'sale-' + (row.branch_id ?? 'none')" class="border-t">
                    <td class="p-3">{{ row.branch_name }}</td>
                    <td class="p-3 font-bold text-green-700">{{ formatPrice(row.total_sales) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <div v-if="aggregateAllBranches && branchExpenseSummary && branchExpenseSummary.length" class="mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3 text-end">توزيع المصروفات حسب الفرع</h3>
            <div class="overflow-x-auto rounded-lg border">
              <table class="w-full bg-white text-end">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="p-3">الفرع</th>
                    <th class="p-3">إجمالي المصروفات</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in branchExpenseSummary" :key="'exp-' + (row.branch_id ?? 'central')" class="border-t">
                    <td class="p-3">{{ row.branch_name }}</td>
                    <td class="p-3 font-bold text-red-700">{{ formatPrice(row.total_expenses) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-if="aggregateAllBranches && branchSalarySummary && branchSalarySummary.length" class="mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3 text-end">توزيع الرواتب المسلمة حسب الفرع</h3>
            <p class="text-sm text-gray-600 mb-2 text-end">يُحسب كل صف من المبالغ المسلَّمة فعلاً فقط خلال الفترة المحددة.</p>
            <div class="overflow-x-auto rounded-lg border">
              <table class="w-full bg-white text-end">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="p-3">الفرع</th>
                    <th class="p-3">إجمالي المسلم</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in branchSalarySummary" :key="'sal-' + (row.branch_id ?? 'none')" class="border-t">
                    <td class="p-3">{{ row.branch_name }}</td>
                    <td class="p-3 font-bold text-orange-700">{{ formatPrice(row.total_salaries) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { router } from '@inertiajs/vue3';
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
    totalSalaries: Number,
    categories: Array,
    products: Array,
    aggregateAllBranches: {
      type: Boolean,
      default: false,
    },
    branchSalesSummary: {
      type: Array,
      default: () => [],
    },
    branchExpenseSummary: {
      type: Array,
      default: () => [],
    },
    branchSalarySummary: {
      type: Array,
      default: () => [],
    },
    salesReportHub: {
      type: Boolean,
      default: false,
    },
    hub_report_branch_id: {
      type: [Number, String],
      default: null,
    },
    reportBranches: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      dateFrom: this.date_from || this.date || '', // سنقوم بتعيين التاريخ الصحيح في mounted
      dateTo: this.date_to || '', // اجعل النهاية فارغة افتراضيًا
      selectedCategoryId: this.category_id || '',
      selectedProductId: this.product_id || '',
      selectedReportBranchId:
        this.hub_report_branch_id !== null && this.hub_report_branch_id !== ''
          ? String(this.hub_report_branch_id)
          : '',
    };
  },
  watch: {
    hub_report_branch_id(val) {
      this.selectedReportBranchId =
        val !== null && val !== undefined && val !== '' ? String(val) : '';
    },
  },
  computed: {
    filteredProducts() {
      if (!this.selectedCategoryId) {
        return this.products;
      }
      return this.products.filter(product => product.category_id == this.selectedCategoryId);
    },
    netTotal() {
      return Number(this.totalSales || 0) - Number(this.totalExpenses || 0) - Number(this.totalSalaries || 0);
    },
  },
  mounted() {
    console.log('تم تحميل الصفحة');
    console.log('date_from:', this.date_from);
    console.log('date:', this.date);
    console.log('date_to:', this.date_to);
    
    // إذا لم يكن هناك تاريخ محدد، قم بجلب البيانات للتاريخ الصحيح
    if (!this.date_from && !this.date && !this.date_to) {
      // تحديث التاريخ الافتراضي بناءً على الوقت الحالي
      this.dateFrom = this.getTodayDate();
      console.log('التاريخ المحدد في mounted:', this.dateFrom);
      this.fetchSales();
    } else {
      console.log('تم تمرير تاريخ من الخادم:', this.dateFrom);
    }
  },
  methods: {
    // دالة للحصول على تاريخ اليوم الحالي مع مراعاة الساعة 7 صباحاً
    getTodayDate() {
      const now = new Date();
      const currentHour = now.getHours();
      
      console.log('الوقت الحالي:', now.toLocaleString('ar-EG'));
      console.log('الساعة الحالية:', currentHour);
      
      // إذا كان الوقت قبل الساعة 7 صباحاً، نعرض مبيعات اليوم السابق
      // إذا كان الوقت بعد الساعة 7 صباحاً، نعرض مبيعات اليوم الحالي
      if (currentHour < 7) {
        // قبل الساعة 7 صباحاً - نعرض مبيعات اليوم السابق من 7 صباحاً إلى 7 صباحاً اليوم الحالي
        const yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        const result = yesterday.toISOString().slice(0, 10);
        console.log('قبل الساعة 7 - التاريخ المحدد:', result);
        return result;
      } else {
        // بعد الساعة 7 صباحاً - نعرض مبيعات اليوم الحالي من 7 صباحاً إلى 7 صباحاً للوم التالي
        const result = now.toISOString().slice(0, 10);
        console.log('بعد الساعة 7 - التاريخ المحدد:', result);
        return result;
      }
    },
    fetchSales() {
      const params = { 
        date_from: this.dateFrom,
        category_id: this.selectedCategoryId,
        product_id: this.selectedProductId
      };
      if (this.dateTo) params.date_to = this.dateTo;
      if (this.salesReportHub && this.selectedReportBranchId) {
        params.report_branch_id = this.selectedReportBranchId;
      }
      router.get(route("admin.sales.report"), params);
    },
    onCategoryChange() {
      // إعادة تعيين المنتج المحدد عند تغيير الفئة
      this.selectedProductId = '';
      this.fetchSales();
    },
    clearFilters() {
      this.selectedCategoryId = '';
      this.selectedProductId = '';
      this.selectedReportBranchId = '';
      this.dateFrom = this.getTodayDate(); // إعادة تعيين التاريخ الصحيح
      this.dateTo = '';
      this.fetchSales();
    },
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    },
    sizeToArabic(size) {
      if (!size) return 'غير محدد';
      const map = { small: 'صغير', medium: 'وسط', large: 'كبير', extra_large: 'كان كبير' };
      return map[size] || size;
    },
    // دالة الانتقال لصفحة المصروفات مع التاريخ المحدد
    goToExpenses() {
      let expenseParams = {};
      
      // تحديد نوع التاريخ المحدد
      if (this.dateFrom && !this.dateTo) {
        // إذا تم تحديد يوم واحد فقط - نستخدم منطق الفترة الزمنية
        // بدلاً من expense_date، نستخدم from و to لتطبيق منطق 7 صباحاً - 7 صباحاً
        const startDate = new Date(this.dateFrom);
        const endDate = new Date(this.dateFrom);
        endDate.setDate(endDate.getDate() + 1);
        
        expenseParams = {
          from: startDate.toISOString().slice(0, 10),
          to: endDate.toISOString().slice(0, 10)
        };
      } else if (this.dateFrom && this.dateTo) {
        // إذا تم تحديد فترة من-إلى
        expenseParams = {
          from: this.dateFrom,
          to: this.dateTo
        };
      } else {
        // افتراضياً: التاريخ الصحيح بناءً على الوقت الحالي
        const today = this.getTodayDate();
        const startDate = new Date(today);
        const endDate = new Date(today);
        endDate.setDate(endDate.getDate() + 1);
        
        expenseParams = {
          from: startDate.toISOString().slice(0, 10),
          to: endDate.toISOString().slice(0, 10)
        };
      }

      if (this.salesReportHub && this.selectedReportBranchId) {
        expenseParams.expense_branch = String(this.selectedReportBranchId);
      }
      
      router.get(route('expenses.index'), expenseParams);
    },

    // دالة الانتقال لصفحة الموظفين مع التاريخ المحدد
    goToEmployees() {
      // الانتقال إلى صفحة الموظفين (لا تحتاج لمعاملات تاريخ لأنها تعرض اليوم الحالي)
      router.get(route('admin.employees.index'));
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
        // التاريخ الصحيح بناءً على الوقت الحالي
        return this.formatDateForDisplay(this.getTodayDate());
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
    },
    // دالة لعرض الفترة الزمنية المحددة
    getTimeRangeText() {
      if (this.dateFrom && !this.dateTo) {
        // يوم واحد - من 7 صباحاً إلى 7 صباحاً للوم التالي
        const nextDay = new Date(this.dateFrom);
        nextDay.setDate(nextDay.getDate() + 1);
        return `من الساعة 7:00 صباحاً ${this.formatDateForDisplay(this.dateFrom)} إلى الساعة 7:00 صباحاً ${this.formatDateForDisplay(nextDay.toISOString().slice(0, 10))}`;
      } else if (this.dateFrom && this.dateTo) {
        // فترة - من 7 صباحاً اليوم الأول إلى 7 صباحاً اليوم الأخير
        return `من الساعة 7:00 صباحاً ${this.formatDateForDisplay(this.dateFrom)} إلى الساعة 7:00 صباحاً ${this.formatDateForDisplay(this.dateTo)}`;
      } else {
        // اليوم الحالي
        const today = this.getTodayDate();
        const nextDay = new Date(today);
        nextDay.setDate(nextDay.getDate() + 1);
        return `من الساعة 7:00 صباحاً ${this.formatDateForDisplay(today)} إلى الساعة 7:00 صباحاً ${this.formatDateForDisplay(nextDay.toISOString().slice(0, 10))}`;
      }
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
