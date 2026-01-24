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
              <div :class="isAdmin ? 'grid grid-cols-1 md:grid-cols-3 gap-4' : 'grid grid-cols-1 md:grid-cols-2 gap-4'">
                <div>
                  <span class="text-sm text-blue-700">الاسم:</span>
                  <span class="font-semibold text-blue-900">{{ salaryData.employee.name }}</span>
                </div>
                <div>
                  <span class="text-sm text-blue-700">الوظيفة:</span>
                  <span class="font-semibold text-blue-900">{{ salaryData.employee.position || 'غير محدد' }}</span>
                </div>
                <div v-if="isAdmin">
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
                  <div v-if="salaryData.summary.total_discounts > 0" class="text-xs text-red-600 mt-1">
                    خصومات: -{{ formatPrice(salaryData.summary.total_discounts) }}
                  </div>
                  <div v-if="salaryData.summary.total_base_amount && salaryData.summary.total_base_amount !== salaryData.summary.total_amount" class="text-xs text-gray-500 mt-1">
                    المبلغ الأصلي: {{ formatPrice(salaryData.summary.total_base_amount) }}
                  </div>
                </div>
                <div>
                  <span class="text-sm text-green-700">أيام العمل:</span>
                  <div class="font-semibold text-green-900">{{ salaryData.summary.days_with_records }} من {{ salaryData.summary.days_count }} يوم</div>
                </div>
              </div>
              <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-700">
                  <strong>ملاحظة:</strong> يتم حساب الساعات من الساعة 7:00 صباحاً إلى الساعة 7:00 صباحاً للوم التالي
                </p>
              </div>
            </div>

            <!-- تفاصيل كل يوم -->
            <div class="bg-white border border-gray-200 rounded-lg">
              <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h4 class="text-lg font-semibold text-gray-900">تفاصيل كل يوم</h4>
                <!-- زر تسليم الكل -->
                <button
                  v-if="canManageEmployees && pendingDaysCount > 0"
                  @click="deliverAllPendingSalaries"
                  :disabled="loading"
                  class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50 transition duration-200"
                >
                  <span v-if="loading">جاري التسليم...</span>
                  <span v-else>💰 تسليم كل المعلق ({{ pendingDaysCount }} أيام)</span>
                </button>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">التاريخ</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">اليوم</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">الساعات</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">المبلغ</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">حالة الراتب</th>
                      <th class="p-4 text-right text-sm font-medium text-gray-700">تفاصيل الحضور</th>
                      <th v-if="canManageEmployees" class="p-4 text-right text-sm font-medium text-gray-700">إجراءات</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="day in salaryData.daily_details" :key="day.date" class="border-t border-gray-200">
                      <td class="p-4 text-sm text-gray-900">{{ day.date_arabic }}</td>
                      <td class="p-4 text-sm text-gray-600">{{ day.day_name }}</td>
                      <td class="p-4 font-semibold text-blue-600">
                        {{ day.hours }} ساعة
                      </td>
                      <td class="p-4">
                        <div class="font-semibold text-green-600">
                          {{ formatPrice(day.amount) }}
                        </div>
                        <div v-if="day.discount_total > 0" class="text-xs text-red-600 mt-1 space-y-1">
                          <div>خصومات: -{{ formatPrice(day.discount_total) }}</div>
                          <div v-if="day.base_amount" class="text-gray-500">
                            المبلغ الأصلي: {{ formatPrice(day.base_amount) }}
                          </div>
                          <div v-if="day.discounts && day.discounts.length > 0" class="mt-1">
                            <details class="text-xs">
                              <summary class="cursor-pointer text-orange-600 hover:text-orange-700">
                                تفاصيل الخصومات ({{ day.discounts.length }})
                              </summary>
                              <div class="mt-1 space-y-1 pr-2">
                                <div v-for="discount in day.discounts" :key="discount.id" class="border-r-2 border-orange-300 pr-2">
                                  <div class="font-medium">-{{ formatPrice(discount.amount) }}</div>
                                  <div v-if="discount.reason" class="text-gray-600 text-xs">{{ discount.reason }}</div>
                                  <div class="text-gray-400 text-xs">{{ discount.created_at }}</div>
                                </div>
                              </div>
                            </details>
                          </div>
                        </div>
                      </td>
                      <td class="p-4">
                        <div v-if="day.delivery_status" class="space-y-1">
                          <span
                            :class="[
                              'px-3 py-1 rounded-full text-xs font-medium inline-block',
                              day.delivery_status.is_delivered
                                ? 'bg-green-100 text-green-800'
                                : (day.amount > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600')
                            ]"
                          >
                            {{ day.delivery_status.is_delivered ? '✅ تم التسليم' : (day.amount > 0 ? '⏳ في الانتظار' : '❌ لا يوجد مبلغ') }}
                          </span>
                          <div v-if="day.delivery_status.is_delivered && day.delivery_status.delivered_at" class="text-xs text-gray-500 mt-1">
                            {{ day.delivery_status.delivered_at }}
                          </div>
                          <div v-if="day.delivery_status.is_delivered && day.delivery_status.delivered_amount" class="text-xs text-green-600">
                            المبلغ المسلم: {{ formatPrice(day.delivery_status.delivered_amount) }}
                          </div>
                        </div>
                        <div v-else-if="day.amount > 0" class="space-y-1">
                          <span class="px-3 py-1 rounded-full text-xs font-medium inline-block bg-gray-100 text-gray-600">
                            ❌ لم يتم إنشاء سجل تسليم
                          </span>
                        </div>
                        <div v-else>
                          <span class="px-3 py-1 rounded-full text-xs font-medium inline-block bg-gray-100 text-gray-500">
                            ➖ لا يوجد عمل
                          </span>
                        </div>
                      </td>
                      <td class="p-4">
                        <div v-if="day.has_records" class="space-y-1">
                          <div v-for="record in day.records" :key="`${day.date}-${record.checkin_time}`" class="text-xs">
                            <span class="text-blue-600">{{ record.checkin_time }}</span>
                            <span class="mx-1">-</span>
                            <span class="text-red-600">{{ record.checkout_time }}</span>
                            <span class="mx-2">({{ record.hours }} ساعة)</span>
                            <span v-if="!record.is_completed" class="text-orange-600 text-xs">قيد العمل</span>
                          </div>
                        </div>
                        <span v-else class="text-gray-400 text-sm">لا يوجد حضور في هذا اليوم</span>
                      </td>
                      <!-- خلية الإجراءات -->
                      <td v-if="canManageEmployees" class="p-4">
                        <div class="flex gap-2">
                          <!-- زر تسليم لليوم -->
                          <button
                            v-if="day.amount > 0 && (!day.delivery_status || !day.delivery_status.is_delivered)"
                            @click="deliverSalaryForDay(day)"
                            :disabled="loading"
                            class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs font-medium disabled:opacity-50 transition duration-200"
                            :title="`تسليم راتب ${day.day_name} ${day.date_arabic}`"
                          >
                            💰 تسليم
                          </button>
                          <!-- زر إلغاء التسليم (للأدمن فقط) -->
                          <button
                            v-if="isAdmin && day.delivery_status && day.delivery_status.is_delivered"
                            @click="undoDeliveryForDay(day)"
                            :disabled="loading"
                            class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-xs font-medium disabled:opacity-50 transition duration-200"
                            title="إلغاء تسليم الراتب (للأدمن فقط)"
                          >
                            ↩️ إلغاء
                          </button>
                        </div>
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
                  <div v-if="salaryData.summary.total_discounts > 0" class="text-xs text-red-600 mt-1">
                    (بعد خصم {{ formatPrice(salaryData.summary.total_discounts) }})
                  </div>
                </div>
              </div>
            </div>

            <!-- إحصائيات التسليم -->
            <div class="bg-orange-50 p-6 rounded-lg">
              <h4 class="text-lg font-semibold text-orange-900 mb-4">إحصائيات التسليم</h4>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-600">{{ deliveredDaysCount }}</div>
                  <div class="text-sm text-green-700">أيام تم تسليمها</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-yellow-600">{{ pendingDaysCount }}</div>
                  <div class="text-sm text-yellow-700">أيام في الانتظار</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-600">{{ formatPrice(deliveredAmount) }}</div>
                  <div class="text-sm text-green-700">المبلغ المسلم</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-yellow-600">{{ formatPrice(pendingAmount) }}</div>
                  <div class="text-sm text-yellow-700">المبلغ المتبقي</div>
                </div>
              </div>
            </div>

            <!-- معلومات التشخيص (للإدارة فقط) -->
            <div v-if="isAdmin" class="bg-gray-50 p-4 rounded-lg mt-4">
              <h5 class="text-sm font-semibold text-gray-700 mb-2">معلومات التشخيص:</h5>
              <div class="text-xs text-gray-600 space-y-1">
                <div>إجمالي سجلات الحضور الموجودة: {{ salaryData.debug_info?.total_attendances_found || 0 }}</div>
                <div>فترة البحث: {{ salaryData.debug_info?.period_start }} - {{ salaryData.debug_info?.period_end }}</div>
              </div>
            </div>
          </div>

          <!-- رسالة نجح أو خطأ -->
          <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4 mt-6">
            <div class="text-green-800">{{ successMessage }}</div>
          </div>
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
      successMessage: null,
    };
  },
  computed: {
    canCalculate() {
      return this.selectedEmployee && this.dateFrom && this.dateTo && this.dateFrom <= this.dateTo;
    },
    isAdmin() {
      return this.$page.props.auth.user?.roles?.includes('admin');
    },
    canManageEmployees() {
      return this.$page.props.auth.user?.permissions?.includes('manage employee attendance') ||
             this.$page.props.auth.user?.roles?.includes('admin') ||
             this.$page.props.auth.user?.roles?.includes('cashier');
    },
    // إحصائيات التسليم
    deliveredDaysCount() {
      if (!this.salaryData) return 0;
      return this.salaryData.daily_details.filter(day => 
        day.delivery_status && day.delivery_status.is_delivered
      ).length;
    },
    pendingDaysCount() {
      if (!this.salaryData) return 0;
      return this.salaryData.daily_details.filter(day => 
        day.amount > 0 && (!day.delivery_status || !day.delivery_status.is_delivered)
      ).length;
    },
    deliveredAmount() {
      if (!this.salaryData) return 0;
      return this.salaryData.daily_details
        .filter(day => day.delivery_status && day.delivery_status.is_delivered)
        .reduce((total, day) => total + (day.delivery_status.delivered_amount || 0), 0);
    },
    pendingAmount() {
      if (!this.salaryData) return 0;
      return this.salaryData.daily_details
        .filter(day => day.amount > 0 && (!day.delivery_status || !day.delivery_status.is_delivered))
        .reduce((total, day) => total + day.amount, 0);
    },
  },
  methods: {
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    },
    
    resetResults() {
      this.salaryData = null;
      this.error = null;
      this.successMessage = null;
    },
    
    clearMessages() {
      this.error = null;
      this.successMessage = null;
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
    
    async deliverSalaryForDay(day) {
      if (!confirm(`هل تريد تسليم راتب ${day.day_name} ${day.date_arabic} بمبلغ ${this.formatPrice(day.amount)} ؟`)) {
        return;
      }

      this.clearMessages();
      this.loading = true;
      
      try {
        const response = await fetch(route('admin.employees.deliver-salary-for-date', this.selectedEmployee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            date: day.date,
          }),
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة اليوم في البيانات المحلية
          const dayIndex = this.salaryData.daily_details.findIndex(d => d.date === day.date);
          if (dayIndex !== -1) {
            this.salaryData.daily_details[dayIndex].delivery_status = {
              is_delivered: true,
              status: data.delivery.status,
              status_text: data.delivery.status_text,
              delivered_at: data.delivery.delivered_at,
              delivered_amount: data.delivery.total_amount
            };
          }

          this.successMessage = `تم تسليم راتب ${day.day_name} ${day.date_arabic} بمبلغ ${this.formatPrice(data.delivery.total_amount)} بنجاح!`;
          
          // مسح رسالة النجاح بعد 5 ثواني
          setTimeout(() => {
            this.successMessage = null;
          }, 5000);
        } else {
          this.error = data.message || 'حدث خطأ أثناء تسليم الراتب';
        }
      } catch (error) {
        console.error('Error:', error);
        this.error = 'حدث خطأ في الاتصال بالخادم';
      } finally {
        this.loading = false;
      }
    },
    
    async deliverAllPendingSalaries() {
      this.clearMessages();
      
      const pendingDays = this.salaryData.daily_details.filter(day => 
        day.amount > 0 && (!day.delivery_status || !day.delivery_status.is_delivered)
      );
      
      if (pendingDays.length === 0) {
        this.error = 'لا توجد أيام معلقة للتسليم';
        return;
      }

      const totalAmount = pendingDays.reduce((sum, day) => sum + day.amount, 0);
      
      if (!confirm(`هل تريد تسليم رواتب كل الأيام المعلقة؟\n\nعدد الأيام: ${pendingDays.length}\nإجمالي المبلغ: ${this.formatPrice(totalAmount)}`)) {
        return;
      }

      this.loading = true;
      console.log('بدء عملية تسليم الرواتب للفترة من', this.dateFrom, 'إلى', this.dateTo);
      
      try {
        const response = await fetch(route('admin.employees.deliver-salary-for-period', this.selectedEmployee.id), {
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

        console.log('استجابة الخادم:', response.status);
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('بيانات الاستجابة:', data);

        if (data.success) {
          // تحديث حالة الأيام المسلمة في البيانات المحلية
          data.delivered_days.forEach(deliveredDay => {
            const dayIndex = this.salaryData.daily_details.findIndex(d => d.date === deliveredDay.date);
            if (dayIndex !== -1) {
              this.salaryData.daily_details[dayIndex].delivery_status = {
                is_delivered: true,
                status: 'delivered',
                status_text: 'تم التسليم',
                delivered_at: new Date().toLocaleDateString('ar-EG') + ' ' + new Date().toLocaleTimeString('ar-EG'),
                delivered_amount: deliveredDay.amount
              };
            }
          });

          // عرض رسالة نجاح مفصلة
          let message = `تم تسليم رواتب ${data.summary.total_days_delivered} أيام بنجاح! `;
          message += `إجمالي المبلغ: ${this.formatPrice(data.summary.total_amount_delivered)}`;
          
          if (data.summary.total_days_skipped > 0) {
            message += ` (تم تخطي ${data.summary.total_days_skipped} أيام لأنها مسلمة مسبقاً)`;
          }

          this.successMessage = message;
          
          // مسح رسالة النجاح بعد 10 ثواني
          setTimeout(() => {
            this.successMessage = null;
          }, 10000);
        } else {
          this.error = data.message || 'حدث خطأ أثناء تسليم الرواتب';
        }
      } catch (error) {
        console.error('Error delivering salaries:', error);
        this.error = `حدث خطأ في الاتصال بالخادم: ${error.message}`;
      } finally {
        this.loading = false;
      }
    },
    
    async undoDeliveryForDay(day) {
      if (!confirm(`هل تريد إلغاء تسليم راتب ${day.day_name} ${day.date_arabic} ؟`)) {
        return;
      }

      this.clearMessages();
      this.loading = true;
      
      try {
        const response = await fetch(route('admin.employees.undo-salary-delivery-for-date', this.selectedEmployee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            date: day.date,
          }),
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة اليوم في البيانات المحلية
          const dayIndex = this.salaryData.daily_details.findIndex(d => d.date === day.date);
          if (dayIndex !== -1) {
            this.salaryData.daily_details[dayIndex].delivery_status = {
              is_delivered: false,
              status: data.delivery.status,
              status_text: data.delivery.status_text,
              delivered_at: null,
              delivered_amount: data.delivery.total_amount
            };
          }

          this.successMessage = `تم إلغاء تسليم راتب ${day.day_name} ${day.date_arabic} بنجاح!`;
          
          // مسح رسالة النجاح بعد 5 ثواني
          setTimeout(() => {
            this.successMessage = null;
          }, 5000);
        } else {
          this.error = data.message || 'حدث خطأ أثناء إلغاء تسليم الراتب';
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