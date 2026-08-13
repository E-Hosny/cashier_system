<template>
  <AppLayout title="مسحوبات الرواتب الثابتة">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        📤 مسحوبات الرواتب الثابتة
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between mb-6">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">تقرير المسحوبات الشهري</h3>
              <p class="text-sm text-gray-600">عرض مسحوبات ومتبقي راتب كل موظف بنظام الراتب الثابت</p>
            </div>
            <div class="flex flex-wrap gap-3 items-end">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الشهر</label>
                <input
                  type="month"
                  v-model="filters.month"
                  class="rounded-lg border border-gray-300 px-3 py-2 text-sm"
                  @change="applyFilters"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الموظف</label>
                <select
                  v-model="filters.employee_id"
                  class="rounded-lg border border-gray-300 px-3 py-2 text-sm min-w-[180px]"
                  @change="applyFilters"
                >
                  <option value="">الكل</option>
                  <option v-for="emp in employeeFilterOptions" :key="emp.id" :value="String(emp.id)">
                    {{ emp.name }}
                  </option>
                </select>
              </div>
              <Link
                :href="route('admin.employees.index')"
                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm"
              >
                رجوع للموظفين
              </Link>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-indigo-50 p-4 rounded-lg">
              <div class="text-indigo-600 text-2xl font-bold">{{ formatPrice(totals.fixed_salary) }}</div>
              <div class="text-indigo-800 text-sm">إجمالي الرواتب الثابتة</div>
            </div>
            <div class="bg-amber-50 p-4 rounded-lg">
              <div class="text-amber-600 text-2xl font-bold">{{ formatPrice(totals.withdrawals_total) }}</div>
              <div class="text-amber-800 text-sm">إجمالي المسحوبات</div>
            </div>
            <div class="bg-red-50 p-4 rounded-lg">
              <div class="text-red-600 text-2xl font-bold">{{ formatPrice(totals.discounts_total) }}</div>
              <div class="text-red-800 text-sm">إجمالي الخصومات</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
              <div class="text-green-600 text-2xl font-bold">{{ formatPrice(totals.remaining) }}</div>
              <div class="text-green-800 text-sm">إجمالي المتبقي</div>
            </div>
          </div>

          <div v-if="employees.length === 0" class="text-center py-12 text-gray-500">
            لا يوجد موظفون برواتب ثابتة{{ selectedEmployeeId ? ' مطابقون للفلتر' : '' }}
          </div>

          <div v-for="employee in employees" :key="employee.id" class="border border-gray-200 rounded-xl mb-4 overflow-hidden">
            <button
              type="button"
              class="w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 bg-gray-50 hover:bg-gray-100 text-right"
              @click="toggleEmployee(employee.id)"
            >
              <div>
                <div class="font-semibold text-gray-900 text-lg">{{ employee.name }}</div>
                <div class="text-sm text-gray-500">{{ employee.position || 'بدون وظيفة' }}</div>
              </div>
              <div class="flex flex-wrap gap-3 text-sm">
                <span class="bg-white px-3 py-1 rounded border">الراتب: <strong>{{ formatPrice(employee.fixed_salary) }}</strong></span>
                <span class="bg-amber-100 text-amber-900 px-3 py-1 rounded">مسحوب: <strong>{{ formatPrice(employee.withdrawals_total) }}</strong></span>
                <span v-if="employee.discounts_total > 0" class="bg-red-100 text-red-900 px-3 py-1 rounded">خصم: <strong>{{ formatPrice(employee.discounts_total) }}</strong></span>
                <span class="bg-green-100 text-green-900 px-3 py-1 rounded">متبقي: <strong>{{ formatPrice(employee.remaining) }}</strong></span>
                <span class="text-gray-500">{{ expanded[employee.id] ? '▲' : '▼' }}</span>
              </div>
            </button>

            <div v-if="expanded[employee.id]" class="p-4 space-y-4">
              <div>
                <h4 class="font-semibold text-gray-800 mb-2">تفاصيل المسحوبات ({{ employee.withdrawals_count }})</h4>
                <div v-if="employee.withdrawals.length === 0" class="text-sm text-gray-500">لا توجد مسحوبات في هذا الشهر</div>
                <div v-else class="overflow-x-auto">
                  <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                      <tr>
                        <th class="p-2 text-right">التاريخ</th>
                        <th class="p-2 text-right">المبلغ</th>
                        <th class="p-2 text-right">بواسطة</th>
                        <th class="p-2 text-right">ملاحظات</th>
                        <th class="p-2 text-right">إجراء</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="w in employee.withdrawals" :key="w.id" class="border-t">
                        <td class="p-2">{{ w.withdrawal_date }}</td>
                        <td class="p-2 font-bold text-amber-700">{{ formatPrice(w.amount) }}</td>
                        <td class="p-2">{{ w.created_by_name || '—' }}</td>
                        <td class="p-2 text-gray-600">{{ w.notes || '—' }}</td>
                        <td class="p-2">
                          <button
                            type="button"
                            class="text-red-600 hover:text-red-800 text-xs font-semibold"
                            :disabled="loading"
                            @click="cancelWithdrawal(employee, w)"
                          >
                            إلغاء
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-if="employee.discounts.length > 0">
                <h4 class="font-semibold text-gray-800 mb-2">خصومات الشهر</h4>
                <ul class="space-y-1 text-sm">
                  <li v-for="d in employee.discounts" :key="d.id" class="flex justify-between gap-2 border-r-2 border-red-300 pr-2">
                    <span>
                      {{ d.discount_date }}
                      <span v-if="d.reason" class="text-gray-500">— {{ d.reason }}</span>
                      <span v-if="d.source === 'late_rule'" class="text-[10px] bg-amber-100 text-amber-800 px-1 rounded mr-1">تأخير</span>
                    </span>
                    <span class="text-red-600 font-medium">-{{ formatPrice(d.amount) }}</span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';

export default {
  layout: AppLayout,
  components: { Link },
  props: {
    month: { type: String, required: true },
    employees: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    employeeFilterOptions: { type: Array, default: () => [] },
    selectedEmployeeId: { type: Number, default: null },
  },
  data() {
    return {
      loading: false,
      expanded: {},
      filters: {
        month: this.month,
        employee_id: this.selectedEmployeeId ? String(this.selectedEmployeeId) : '',
      },
    };
  },
  methods: {
    formatPrice(price) {
      return price != null ? Number(price).toFixed(2) : '0.00';
    },
    applyFilters() {
      const params = { month: this.filters.month };
      if (this.filters.employee_id) {
        params.employee_id = this.filters.employee_id;
      }
      router.get(route('admin.employees.salary-withdrawals'), params, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    toggleEmployee(id) {
      this.expanded = {
        ...this.expanded,
        [id]: !this.expanded[id],
      };
    },
    async cancelWithdrawal(employee, withdrawal) {
      if (!confirm(`إلغاء مسحوب بقيمة ${this.formatPrice(withdrawal.amount)} لـ ${employee.name}؟\nسيتم حذف المصروف المرتبط أيضاً.`)) {
        return;
      }

      this.loading = true;
      try {
        const response = await fetch(
          route('admin.employees.salary-withdrawals.cancel', [employee.id, withdrawal.id]),
          {
            method: 'DELETE',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
              Accept: 'application/json',
            },
          }
        );
        const data = await response.json();
        if (data.success) {
          alert(data.message || 'تم الإلغاء');
          router.reload({ preserveScroll: true });
        } else {
          alert(data.message || 'تعذر الإلغاء');
        }
      } catch (e) {
        console.error(e);
        alert('حدث خطأ في الاتصال بالخادم');
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
