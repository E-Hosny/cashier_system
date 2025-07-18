<template>
  <AppLayout title="إدارة الموظفين">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        👥 إدارة الموظفين - الحضور والانصراف
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- رأس الصفحة -->
          <div class="mb-6 flex justify-between items-center">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">قائمة الموظفين</h3>
              <p class="text-sm text-gray-600">إدارة حضور وانصراف الموظفين</p>
            </div>
            <div>
              <Link
                :href="route('admin.employees.create')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200"
              >
                ➕ إضافة موظف جديد
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
              <div class="text-green-600 text-2xl font-bold">{{ presentEmployees.length }}</div>
              <div class="text-green-800 text-sm">الموظفين الحاضرين</div>
            </div>
            <div class="bg-yellow-50 p-4 rounded-lg">
              <div class="text-yellow-600 text-2xl font-bold">{{ absentEmployees.length }}</div>
              <div class="text-yellow-800 text-sm">الموظفين الغائبين</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
              <div class="text-purple-600 text-2xl font-bold">{{ formatPrice(updatedTotalTodayAmount) }}</div>
              <div class="text-purple-800 text-sm">إجمالي الرواتب اليوم</div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg">
              <div class="text-orange-600 text-2xl font-bold">{{ updatedTotalTodayHours.toFixed(2) }}</div>
              <div class="text-orange-800 text-sm">إجمالي الساعات اليوم</div>
            </div>
          </div>

          <!-- جدول الموظفين -->
          <div class="overflow-x-auto">
            <table class="w-full bg-white rounded-lg text-end">
              <thead class="bg-gray-100">
                <tr class="text-gray-700">
                  <th class="p-4 text-right">الموظف</th>
                  <th class="p-4 text-right">الوظيفة</th>
                  <th class="p-4 text-right">سعر الساعة</th>
                  <th class="p-4 text-right">الحالة</th>
                  <th class="p-4 text-right">سجلات الحضور اليوم</th>
                  <th class="p-4 text-right">الساعات اليوم</th>
                  <th class="p-4 text-right">المبلغ اليوم</th>
                  <th class="p-4 text-right">الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="employees.length === 0" class="border-t">
                  <td colspan="8" class="text-center p-6 text-gray-500">
                    لا يوجد موظفين مسجلين
                  </td>
                </tr>
                <tr v-for="employee in employees" :key="employee.id" class="border-t hover:bg-gray-50">
                  <td class="p-4">
                    <div class="font-semibold">{{ employee.name }}</div>
                    <div class="text-sm text-gray-500">{{ employee.phone || 'لا يوجد رقم' }}</div>
                  </td>
                  <td class="p-4 text-gray-600">{{ employee.position || 'غير محدد' }}</td>
                  <td class="p-4 font-bold text-green-600">{{ formatPrice(employee.hourly_rate) }}</td>
                  <td class="p-4">
                    <span
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
                  <td class="p-4 font-bold text-green-600">
                    {{ formatPrice(employee.today_amount) }}
                  </td>
                  <td class="p-4">
                    <div class="flex gap-2 flex-wrap">
                      <!-- زر الحضور -->
                      <button
                        v-if="!employee.is_present"
                        @click="checkinEmployee(employee)"
                        :disabled="loading"
                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                      >
                        ✅ حضور
                      </button>
                      
                      <!-- زر الانصراف -->
                      <button
                        v-if="employee.is_present"
                        @click="checkoutEmployee(employee)"
                        :disabled="loading"
                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm font-medium disabled:opacity-50"
                      >
                        🚪 انصراف
                      </button>

                      <!-- زر التعديل -->
                      <Link
                        :href="route('admin.employees.edit', employee.id)"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium"
                      >
                        ✏️ تعديل
                      </Link>


                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>


        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

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
  },
  data() {
    return {
      loading: false,
    };
  },
  computed: {
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
  },
  methods: {
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


  },
  };
</script> 