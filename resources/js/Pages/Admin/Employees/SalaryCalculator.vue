<template>
  <AppLayout title="حاسبة الرواتب">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        💰 حاسبة الرواتب
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- رأس الصفحة -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">حساب راتب موظف لفترة محددة</h3>
            <p class="text-sm text-gray-600">احسب راتب موظف محدد خلال فترة زمنية محددة (من 7 صباحاً إلى 7 صباحاً للوم التالي)</p>
          </div>

          <!-- نموذج اختيار الموظف والفترة -->
          <div class="bg-gray-50 p-6 rounded-lg mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- اختيار الموظف -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">اختر الموظف</label>
                <select 
                  v-model="selectedEmployee" 
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  @change="resetResults"
                >
                  <option value="">-- اختر موظف --</option>
                  <option v-for="employee in employees" :key="employee.id" :value="employee">
                    {{ employee.name }} ({{ employee.position || 'غير محدد' }})
                  </option>
                </select>
              </div>

              <!-- تاريخ البداية -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">من تاريخ</label>
                <input 
                  type="date" 
                  v-model="dateFrom"
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  @change="resetResults"
                />
              </div>

              <!-- تاريخ النهاية -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">إلى تاريخ</label>
                <input 
                  type="date" 
                  v-model="dateTo"
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                  @change="resetResults"
                />
              </div>
            </div>

            <!-- زر الحساب -->
            <div class="mt-4">
              <button 
                @click="calculateSalary"
                :disabled="!canCalculate || loading"
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-6 py-2 rounded-lg font-medium transition duration-200"
              >
                <span v-if="loading">جاري الحساب...</span>
                <span v-else>💰 احسب الراتب</span>
              </button>
            </div>
          </div>

          <!-- نتائج الحساب -->
          <div v-if="salaryData" class="space-y-6">
            <!-- ملخص الموظف -->
            <div class="bg-blue-50 p-6 rounded-lg">
              <h4 class="text-lg font-semibold text-blue-900 mb-4">معلومات الموظف</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <span class="text-sm text-blue-700">الاسم:</span>
                  <span class="font-semibold text-blue-900">{{ salaryData.employee.name }}</span>
                </div>
                <div>
                  <span class="text-sm text-blue-700">الوظيفة:</span>
                  <span class="font-semibold text-blue-900">{{ salaryData.employee.position || 'غير محدد' }}</span>
                </div>
                <div>
                  <span class="text-sm text-blue-700">سعر الساعة:</span>
                  <span class="font-semibold text-blue-900">{{ formatPrice(salaryData.employee.hourly_rate) }}</span>
                </div>
              </div>
            </div>

            <!-- ملخص الفترة -->
            <div class="bg-green-50 p-6 rounded-lg">
              <h4 class="text-lg font-semibold text-green-900 mb-4">ملخص الفترة</h4>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                  <span class="text-sm text-green-700">الفترة:</span>
                  <div class="font-semibold text-green-900">
                    {{ salaryData.period.date_from_arabic }} - {{ salaryData.period.date_to_arabic }}
                  </div>
                </div>
                <div>
                  <span class="text-sm text-green-700">إجمالي الساعات:</span>
                  <div class="font-semibold text-green-900">{{ salaryData.summary.total_hours }} ساعة</div>
                </div>
                <div>
                  <span class="text-sm text-green-700">إجمالي المبلغ:</span>
                  <div class="font-semibold text-green-900">{{ formatPrice(salaryData.summary.total_amount) }}</div>
                </div>
                <div>
                  <span class="text-sm text-green-700">أيام العمل:</span>
                  <div class="font-semibold text-green-900">{{ salaryData.summary.days_with_records }} من {{ salaryData.summary.days_count }} يوم</div>
                </div>
              </div>
            </div>

            <!-- تفاصيل كل يوم -->
            <div class="bg-white border border-gray-200 rounded-lg">
              <h4 class="text-lg font-semibold text-gray-900 p-6 border-b border-gray-200">تفاصيل كل يوم</h4>
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">التاريخ</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">اليوم</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">الساعات</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">المبلغ</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">تفاصيل الحضور</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="day in salaryData.daily_details" :key="day.date" class="border-t border-gray-200">
                      <td class="p-4 text-sm text-gray-900">{{ day.date_arabic }}</td>
                      <td class="p-4 text-sm text-gray-600">{{ day.day_name }}</td>
                      <td class="p-4 font-semibold text-blue-600">
                        {{ day.hours }} ساعة
                      </td>
                      <td class="p-4 font-semibold text-green-600">
                        {{ formatPrice(day.amount) }}
                      </td>
                      <td class="p-4">
                        <div v-if="day.has_records" class="space-y-1">
                          <div v-for="record in day.records" :key="`${day.date}-${record.checkin_time}`" class="text-xs">
                            <span class="text-blue-600">{{ record.checkin_time }}</span>
                            <span class="mx-1">-</span>
                            <span class="text-red-600">{{ record.checkout_time }}</span>
                            <span class="mx-2">({{ record.hours }} ساعة)</span>
                          </div>
                        </div>
                        <span v-else class="text-gray-400 text-sm">لا يوجد حضور</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- ملخص نهائي -->
            <div class="bg-purple-50 p-6 rounded-lg">
              <h4 class="text-lg font-semibold text-purple-900 mb-4">الملخص النهائي</h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="text-center">
                  <div class="text-3xl font-bold text-purple-600">{{ salaryData.summary.total_hours }}</div>
                  <div class="text-sm text-purple-700">إجمالي الساعات</div>
                </div>
                <div class="text-center">
                  <div class="text-3xl font-bold text-purple-600">{{ formatPrice(salaryData.summary.total_amount) }}</div>
                  <div class="text-sm text-purple-700">إجمالي المبلغ المستحق</div>
                </div>
              </div>
            </div>
          </div>

          <!-- رسالة خطأ -->
          <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mt-6">
            <div class="text-red-800">{{ error }}</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    employees: Array,
  },
  data() {
    return {
      selectedEmployee: null,
      dateFrom: '',
      dateTo: '',
      salaryData: null,
      loading: false,
      error: null,
    };
  },
  computed: {
    canCalculate() {
      return this.selectedEmployee && this.dateFrom && this.dateTo && this.dateFrom <= this.dateTo;
    },
  },
  methods: {
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    },
    
    resetResults() {
      this.salaryData = null;
      this.error = null;
    },

    async calculateSalary() {
      if (!this.canCalculate) return;

      this.loading = true;
      this.error = null;

      try {
        const response = await fetch(route('admin.employees.calculate-salary', this.selectedEmployee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            date_from: this.dateFrom,
            date_to: this.dateTo,
          }),
        });

        const data = await response.json();

        if (data.success) {
          this.salaryData = data;
        } else {
          this.error = data.message || 'حدث خطأ أثناء حساب الراتب';
        }
      } catch (error) {
        console.error('Error:', error);
        this.error = 'حدث خطأ في الاتصال بالخادم';
      } finally {
        this.loading = false;
      }
    },
  },
  mounted() {
    // تعيين التواريخ الافتراضية (الشهر الحالي)
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    this.dateFrom = firstDay.toISOString().split('T')[0];
    this.dateTo = lastDay.toISOString().split('T')[0];
  },
};
</script> 