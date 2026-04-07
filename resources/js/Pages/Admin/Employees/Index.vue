<template>
  <AppLayout title="إدارة الموظفين">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        👥 {{ (isAdmin || isSuperAdmin) ? 'إدارة الموظفين' : 'الحضور والانصراف' }}
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- رأس الصفحة -->
          <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-start">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ (isAdmin || isSuperAdmin) ? 'قائمة الموظفين' : 'الحضور والانصراف' }}</h3>
              <p class="text-sm text-gray-600">{{ (isAdmin || isSuperAdmin) ? 'إدارة حضور وانصراف الموظفين' : 'تسجيل حضور وانصراف الموظفين' }}</p>
              <p class="text-xs text-gray-500 mt-2 max-w-xl leading-relaxed">{{ currentPeriodText }}</p>
            </div>
            <div class="flex flex-col gap-1 shrink-0">
              <label for="employee-day-picker" class="text-sm font-medium text-gray-700">يوم العمل <span class="text-gray-500 font-normal">(7 ص → 7 ص)</span></label>
              <input
                id="employee-day-picker"
                type="date"
                class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                :value="selectedDate"
                :max="maxSelectableDate"
                @change="onBusinessDayChange"
              />
            </div>
            <div v-if="isAdmin || isSuperAdmin" class="flex gap-2">
              <Link
                :href="route('admin.employees.create')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200"
              >
                ➕ إضافة موظف جديد
              </Link>
              <Link
                :href="route('admin.employees.salary-calculator')"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200"
              >
                💰 حاسبة الرواتب
              </Link>
            </div>
          </div>



          <!-- إحصائيات سريعة -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg">
              <div class="text-blue-600 text-2xl font-bold">{{ employees.length }}</div>
              <div class="text-blue-800 text-sm">إجمالي الموظفين</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
              <div class="text-green-600 text-2xl font-bold">{{ isViewingTodayBusinessDay ? presentEmployees.length : '—' }}</div>
              <div class="text-green-800 text-sm">الموظفين الحاضرين الآن</div>
              <div v-if="!isViewingTodayBusinessDay" class="text-xs text-gray-500 mt-1">لليوم الحالي فقط</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
              <div class="text-yellow-600 text-2xl font-bold">{{ isViewingTodayBusinessDay ? absentEmployees.length : '—' }}</div>
              <div class="text-yellow-800 text-sm">الموظفين الغائبين الآن</div>
              <div v-if="!isViewingTodayBusinessDay" class="text-xs text-gray-500 mt-1">لليوم الحالي فقط</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
              <div class="text-purple-600 text-2xl font-bold">{{ formatPrice(updatedTotalTodayAmount) }}</div>
              <div class="text-purple-800 text-sm">{{ isViewingTodayBusinessDay ? 'إجمالي الرواتب اليوم' : 'إجمالي الرواتب للفترة المحددة' }}</div>
              <div v-if="updatedTotalTodayDiscounts > 0" class="text-xs text-red-600 mt-1">
                خصومات: -{{ formatPrice(updatedTotalTodayDiscounts) }}
              </div>
              <div v-if="updatedTotalTodayDiscounts > 0" class="text-xs text-gray-600 mt-1">
                المبلغ الأصلي: {{ formatPrice(updatedTotalTodayAmount + updatedTotalTodayDiscounts) }}
              </div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg">
              <div class="text-orange-600 text-2xl font-bold">{{ updatedTotalTodayHours.toFixed(2) }}</div>
              <div class="text-orange-800 text-sm">{{ isViewingTodayBusinessDay ? 'إجمالي الساعات اليوم' : 'إجمالي الساعات للفترة المحددة' }}</div>
            </div>
          </div>

          <!-- جدول الموظفين -->
          <div class="overflow-x-auto">
            <table class="w-full bg-white rounded-lg text-end">
              <thead class="bg-gray-100">
                <tr class="text-gray-700">
                  <th class="p-4 text-right">الموظف</th>
                  <th class="p-4 text-right">الوظيفة</th>
                  <th v-if="isAdmin" class="p-4 text-right">سعر الساعة</th>
                  <th class="p-4 text-right">الحالة</th>
                  <th class="p-4 text-right">سجلات الحضور</th>
                  <th class="p-4 text-right">{{ isViewingTodayBusinessDay ? 'الساعات اليوم' : 'الساعات (الفترة)' }}</th>
                  <th class="p-4 text-right">{{ isViewingTodayBusinessDay ? 'المبلغ اليوم' : 'المبلغ (الفترة)' }}</th>
                  <th class="p-4 text-right">حالة الراتب</th>
                  <th class="p-4 text-right">الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="employees.length === 0" class="border-t">
                  <td :colspan="isAdmin ? '9' : '8'" class="text-center p-6 text-gray-500">
                    لا يوجد موظفين مسجلين
                  </td>
                </tr>
                <tr v-for="employee in employees" :key="employee.id" class="border-t hover:bg-gray-50">
                  <td class="p-4">
                    <div class="font-semibold">{{ employee.name }}</div>
                    <div class="text-sm text-gray-500">{{ employee.phone || 'لا يوجد رقم' }}</div>
                    <div v-if="employee.attendance_dependency_employee_name" class="text-xs text-amber-700 mt-1">
                      مرتبط حضور مع: {{ employee.attendance_dependency_employee_name }}
                    </div>
                  </td>
                  <td class="p-4 text-gray-600">{{ employee.position || 'غير محدد' }}</td>
                  <td v-if="isAdmin" class="p-4 font-bold text-green-600">{{ formatPrice(employee.hourly_rate) }}</td>
                  <td class="p-4">
                    <span
                      v-if="isViewingTodayBusinessDay"
                      :class="[
                        'px-3 py-1 rounded-full text-xs font-medium',
                        employee.is_present
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'
                      ]"
                      @click="checkEmployeeStatus(employee)"
                      style="cursor: pointer;"
                      title="اضغط للتحقق من الحالة"
                    >
                      {{ employee.is_present ? '🟢 حاضر' : '🔴 غائب' }}
                    </span>
                    <span v-else class="text-gray-400 text-sm">—</span>
                  </td>
                  <td class="p-4">
                    <div v-if="employee.today_attendance_records && employee.today_attendance_records.length > 0" class="space-y-1">
                      <div v-for="record in employee.today_attendance_records" :key="record.id" class="text-xs">
                        <div class="flex justify-between items-center">
                          <span class="text-blue-600">حضور: {{ formatTime(record.checkin_time) }}</span>
                          <span v-if="record.checkout_time" class="text-red-600">انصراف: {{ formatTime(record.checkout_time) }}</span>
                          <span v-else class="text-orange-600">قيد العمل</span>
                        </div>
                      </div>
                    </div>
                    <span v-else class="text-gray-400 text-sm">لا توجد سجلات</span>
                  </td>
                  <td class="p-4 font-bold text-blue-600">
                    {{ employee.today_hours.toFixed(2) }} ساعة
                  </td>
                  <td class="p-4">
                    <div class="font-bold text-green-600">
                      {{ formatPrice(employee.today_amount) }}
                    </div>
                    <div v-if="employee.today_discount_total > 0" class="text-xs text-red-600 mt-1 space-y-1">
                      <!-- إذا كان هناك أكثر من خصم، نعرض الإجمالي -->
                      <div v-if="employee.today_discounts && employee.today_discounts.length > 1">
                        خصومات: -{{ formatPrice(employee.today_discount_total) }}
                      </div>
                      <!-- عرض تفاصيل الخصومات -->
                      <div v-if="employee.today_discounts && employee.today_discounts.length > 0" class="mt-1 space-y-1">
                        <div v-for="discount in employee.today_discounts" :key="discount.id" class="border-r-2 border-red-300 pr-2">
                          <div class="font-medium">-{{ formatPrice(discount.amount) }}</div>
                          <div v-if="discount.reason" class="text-gray-600 text-xs mt-0.5">
                            {{ discount.reason }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="p-4">
                    <div class="flex flex-col gap-2">
                      <span
                        :class="[
                          'px-3 py-1 rounded-full text-xs font-medium inline-block',
                          employee.is_salary_delivered
                            ? 'bg-green-100 text-green-800'
                            : (employee.today_amount > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-600')
                        ]"
                      >
                        {{ employee.is_salary_delivered ? '✅ تم التسليم' : (employee.today_amount > 0 ? '⏳ في الانتظار' : '❌ لا يوجد مبلغ') }}
                      </span>
                      <div v-if="employee.is_salary_delivered && employee.today_delivery_status" class="text-xs text-gray-500">
                        تاريخ التسليم: {{ formatDeliveryDate(employee.today_delivery_status.delivered_at) }}
                      </div>
                    </div>
                  </td>
                  <td class="p-4">
                    <div class="flex gap-2 flex-wrap">
                      <!-- زر الحضور -->
                      <button
                        v-if="isViewingTodayBusinessDay && !employee.is_present"
                        @click="checkinEmployee(employee)"
                        :disabled="loading"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                      >
                        ✅ حضور
                      </button>
                      
                      <!-- زر الانصراف -->
                      <button
                        v-if="isViewingTodayBusinessDay && employee.is_present"
                        @click="checkoutEmployee(employee)"
                        :disabled="loading"
                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                      >
                        🚪 انصراف
                      </button>

                      <!-- زر تسليم الراتب (يظهر فقط بعد الانصراف) -->
                      <button
                        v-if="isViewingTodayBusinessDay && canManageEmployees && employee.today_amount > 0 && !employee.is_salary_delivered && !employee.is_present"
                        @click="deliverSalary(employee)"
                        :disabled="loading"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                        title="تسليم راتب اليوم (متاح فقط بعد الانصراف)"
                      >
                        💰 تسليم
                      </button>

                      <!-- زر إلغاء تسليم الراتب (للأدمن فقط) -->
                      <button
                        v-if="isViewingTodayBusinessDay && isAdmin && employee.is_salary_delivered"
                        @click="undoSalaryDelivery(employee)"
                        :disabled="loading"
                        class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                        title="إلغاء تسليم الراتب (للأدمن فقط)"
                      >
                        ↩️ إلغاء التسليم
                      </button>

                      <!-- زر التعديل -->
                      <Link
                        v-if="isAdmin"
                        :href="route('admin.employees.edit', employee.id)"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium"
                      >
                        ✏️ تعديل
                      </Link>

                      <!-- زر الخصم -->
                      <button
                        v-if="canManageEmployees"
                        @click="openDiscountModal(employee)"
                        :disabled="loading"
                        class="bg-orange-600 hover:bg-orange-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                        title="إضافة خصم من راتب اليوم"
                      >
                        💸 خصم
                      </button>

                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Modal إضافة خصم -->
          <div v-if="showDiscountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeDiscountModal">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" dir="rtl">
              <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-900 mb-4">إضافة خصم - {{ selectedEmployee?.name }}</h3>
                
                <form @submit.prevent="submitDiscount">
                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      مبلغ الخصم <span class="text-red-500">*</span>
                    </label>
                    <input
                      type="number"
                      v-model="discountForm.amount"
                      step="0.01"
                      min="0.01"
                      required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="0.00"
                    />
                  </div>
                  
                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      السبب (اختياري)
                    </label>
                    <textarea
                      v-model="discountForm.reason"
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                      placeholder="أدخل سبب الخصم..."
                    ></textarea>
                  </div>
                  
                  <div class="flex gap-2 justify-end">
                    <button
                      type="button"
                      @click="closeDiscountModal"
                      class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition duration-200"
                    >
                      إلغاء
                    </button>
                    <button
                      type="submit"
                      :disabled="loading || !discountForm.amount || discountForm.amount <= 0"
                      class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed transition duration-200"
                    >
                      إضافة خصم
                    </button>
                  </div>
                </form>
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
  components: {
    Link,
  },
  props: {
    employees: Array,
    totalTodayAmount: Number,
    totalTodayHours: Number,
    currentPeriodText: String,
    selectedDate: String,
    maxSelectableDate: String,
    isViewingTodayBusinessDay: { type: Boolean, default: true },
  },
  data() {
    return {
      loading: false,
      showDiscountModal: false,
      selectedEmployee: null,
      discountForm: {
        amount: '',
        reason: '',
      },
    };
  },
    computed: {
    isAdmin() {
      return this.$page.props.auth.user?.roles?.includes('admin');
    },
    isSuperAdmin() {
      return this.$page.props.auth.user?.roles?.includes('super admin');
    },
    canManageEmployees() {
      return this.$page.props.auth.user?.permissions?.includes('manage employee attendance') ||
             this.$page.props.auth.user?.roles?.includes('admin') ||
             this.$page.props.auth.user?.roles?.includes('cashier') ||
             this.$page.props.auth.user?.roles?.includes('super admin');
    },
    presentEmployees() {
      return this.employees.filter(emp => emp.is_present);
    },
    absentEmployees() {
      return this.employees.filter(emp => !emp.is_present);
    },
    // حساب الإحصائيات المحدثة
    updatedTotalTodayAmount() {
      return this.employees.reduce((total, emp) => total + (emp.today_amount || 0), 0);
    },
    updatedTotalTodayHours() {
      return this.employees.reduce((total, emp) => total + (emp.today_hours || 0), 0);
    },
    updatedTotalTodayDiscounts() {
      return this.employees.reduce((total, emp) => total + (emp.today_discount_total || 0), 0);
    },
  },
  methods: {
    onBusinessDayChange(e) {
      const val = e.target.value;
      router.get(route('admin.employees.index'), { date: val }, { preserveState: true, preserveScroll: true });
    },
    formatPrice(price) {
      return price ? Number(price).toFixed(2) : "0.00";
    },
    formatTime(timeString) {
      if (!timeString) return '-';
      const time = new Date(timeString);
      return time.toLocaleTimeString('ar-EG', {
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    formatDeliveryDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleString('ar-EG', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    
    // دالة مساعدة للتحقق من حالة الحضور
    checkEmployeeStatus(employee) {
      console.log('Checking employee status:', {
        name: employee.name,
        is_present: employee.is_present,
        current_attendance: employee.current_attendance,
        has_open_attendance: employee.today_attendance_records && 
          employee.today_attendance_records.some(record => !record.checkout_time)
      });
    },
    async checkinEmployee(employee) {
      this.loading = true;
      try {
        const response = await fetch(route('admin.employees.checkin', employee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة الموظف مباشرة
          employee.is_present = true;
          employee.current_attendance = data.attendance;
          
          // إضافة سجل الحضور الجديد إلى القائمة
          if (!employee.today_attendance_records) {
            employee.today_attendance_records = [];
          }
          employee.today_attendance_records.unshift(data.attendance);
          
          // تحديث الساعات والمبلغ
          employee.today_hours = data.total_hours;
          employee.today_amount = data.total_amount;
          
          console.log('Employee checkin successful:', {
            employee: employee.name,
            is_present: employee.is_present,
            current_attendance: employee.current_attendance,
            today_records: employee.today_attendance_records
          });
          
          alert('تم تسجيل الحضور بنجاح!');
        } else {
          alert(data.message);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تسجيل الحضور');
      } finally {
        this.loading = false;
      }
    },
    async checkoutEmployee(employee) {
      this.loading = true;
      try {
        const response = await fetch(route('admin.employees.checkout', employee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة الموظف مباشرة
          employee.is_present = false;
          employee.current_attendance = null;
          
          // تحديث آخر سجل حضور مع وقت الانصراف
          if (employee.today_attendance_records && employee.today_attendance_records.length > 0) {
            const lastRecord = employee.today_attendance_records[0];
            lastRecord.checkout_time = data.attendance.checkout_time;
          }
          
          // تحديث الساعات والمبلغ
          employee.today_hours = data.total_hours;
          employee.today_amount = data.total_amount;
          
          alert(`تم تسجيل الانصراف بنجاح!\n\nالساعات: ${data.total_hours} ساعة\nالمبلغ: ${this.formatPrice(data.total_amount)}`);
        } else {
          alert(data.message);
        }
      } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ أثناء تسجيل الانصراف');
      } finally {
        this.loading = false;
      }
    },
    
    async deliverSalary(employee) {
      if (!confirm(`هل تريد تسليم راتب ${employee.name} بمبلغ ${this.formatPrice(employee.today_amount)} ؟`)) {
        return;
      }

      this.loading = true;
      try {
        const response = await fetch(route('admin.employees.deliver-salary', employee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة الموظف
          employee.is_salary_delivered = true;
          employee.today_delivery_status = data.delivery;
          employee.delivery_status_text = data.delivery.status_text;

          alert(`تم تسليم الراتب بنجاح!\n\nالموظف: ${employee.name}\nالمبلغ: ${this.formatPrice(data.delivery.total_amount)}\nالساعات: ${data.delivery.hours_worked} ساعة`);
        } else {
          alert(data.message || 'حدث خطأ أثناء تسليم الراتب');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالخادم');
      } finally {
        this.loading = false;
      }
    },
    
    async undoSalaryDelivery(employee) {
      if (!confirm(`هل تريد إلغاء تسليم راتب ${employee.name} بمبلغ ${this.formatPrice(employee.today_amount)} ؟`)) {
        return;
      }

      this.loading = true;
      try {
        const response = await fetch(route('admin.employees.undo-salary-delivery', employee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
        });

        const data = await response.json();

        if (data.success) {
          // تحديث حالة الموظف
          employee.is_salary_delivered = false;
          employee.today_delivery_status = data.delivery;
          employee.delivery_status_text = data.delivery.status_text;

          alert(`تم إلغاء تسليم الراتب بنجاح!\n\nالموظف: ${employee.name}\nالمبلغ: ${this.formatPrice(data.delivery.total_amount)}`);
        } else {
          alert(data.message || 'حدث خطأ أثناء إلغاء تسليم الراتب');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالخادم');
      } finally {
        this.loading = false;
      }
    },

    // فتح modal إضافة خصم
    openDiscountModal(employee) {
      this.selectedEmployee = employee;
      this.discountForm = {
        amount: '',
        reason: '',
      };
      this.showDiscountModal = true;
    },

    // إغلاق modal إضافة خصم
    closeDiscountModal() {
      this.showDiscountModal = false;
      this.selectedEmployee = null;
      this.discountForm = {
        amount: '',
        reason: '',
      };
    },

    // إضافة خصم
    async submitDiscount() {
      if (!this.selectedEmployee) return;

      if (!this.discountForm.amount || this.discountForm.amount <= 0) {
        alert('يرجى إدخال مبلغ خصم صحيح');
        return;
      }

      this.loading = true;
      try {
        const response = await fetch(route('admin.employees.add-discount', this.selectedEmployee.id), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            amount: parseFloat(this.discountForm.amount),
            reason: this.discountForm.reason || null,
            discount_date: this.selectedDate,
          }),
        });

        const data = await response.json();

        if (data.success) {
          // تحديث المبلغ اليومي للموظف
          this.selectedEmployee.today_amount = data.employee.today_amount;
          
          alert(`تم إضافة الخصم بنجاح!\n\nالمبلغ الأصلي: ${this.formatPrice(parseFloat(this.discountForm.amount) + data.employee.today_amount)}\nمبلغ الخصم: ${this.formatPrice(this.discountForm.amount)}\nالمبلغ النهائي: ${this.formatPrice(data.employee.today_amount)}`);
          
          // إغلاق الـ modal
          this.closeDiscountModal();
          
          // إعادة تحميل الصفحة لتحديث البيانات
          window.location.reload();
        } else {
          alert(data.message || 'حدث خطأ أثناء إضافة الخصم');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('حدث خطأ في الاتصال بالخادم');
      } finally {
        this.loading = false;
      }
    },

  },
  };
</script> 