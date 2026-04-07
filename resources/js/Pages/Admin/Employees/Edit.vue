<template>
  <AppLayout title="تعديل بيانات الموظف">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        👥 تعديل بيانات الموظف
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- رأس الصفحة -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">تعديل بيانات الموظف</h3>
            <p class="text-sm text-gray-600">تعديل بيانات: {{ employee.name }}</p>
          </div>

          <!-- نموذج تعديل موظف -->
          <form @submit.prevent="submitForm">
            <!-- اسم الموظف -->
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                اسم الموظف *
              </label>
              <input
                v-model="form.name"
                type="text"
                required
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل اسم الموظف"
              />
              <div v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</div>
            </div>

            <!-- سعر الساعة -->
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                سعر الساعة (جنيه) *
              </label>
              <input
                v-model="form.hourly_rate"
                type="number"
                step="0.01"
                min="0"
                required
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل سعر الساعة"
              />
              <div v-if="errors.hourly_rate" class="text-red-500 text-sm mt-1">{{ errors.hourly_rate }}</div>
            </div>

            <!-- رقم الهاتف -->
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                رقم الهاتف
              </label>
              <input
                v-model="form.phone"
                type="tel"
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل رقم الهاتف"
              />
              <div v-if="errors.phone" class="text-red-500 text-sm mt-1">{{ errors.phone }}</div>
            </div>

            <!-- الوظيفة -->
            <div class="mb-4">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                الوظيفة
              </label>
              <input
                v-model="form.position"
                type="text"
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل الوظيفة"
              />
              <div v-if="errors.position" class="text-red-500 text-sm mt-1">{{ errors.position }}</div>
            </div>

            <!-- حالة الموظف -->
            <div class="mb-4">
              <label class="flex items-center">
                <input
                  v-model="form.is_active"
                  type="checkbox"
                  class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                />
                <span class="mr-2 text-gray-700 text-sm font-bold">نشط</span>
              </label>
              <div v-if="errors.is_active" class="text-red-500 text-sm mt-1">{{ errors.is_active }}</div>
            </div>

            <!-- ملاحظات -->
            <div class="mb-6">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                ملاحظات
              </label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="أدخل أي ملاحظات إضافية"
              ></textarea>
              <div v-if="errors.notes" class="text-red-500 text-sm mt-1">{{ errors.notes }}</div>
            </div>

            <div v-if="canManageAttendanceDependency" class="mb-6">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                ربط الحضور بموظف آخر (اختياري)
              </label>
              <select
                v-model="form.attendance_dependency_employee_id"
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">بدون ربط</option>
                <option v-for="item in employees" :key="item.id" :value="item.id">{{ item.name }}</option>
              </select>
              <p class="text-xs text-gray-500 mt-1">
                عند الربط، لا يمكن تسجيل حضور هذا الموظف إذا كان الموظف المرتبط ما زال في العمل.
              </p>
              <div v-if="errors.attendance_dependency_employee_id" class="text-red-500 text-sm mt-1">{{ errors.attendance_dependency_employee_id }}</div>
            </div>

            <div v-if="canManageAttendanceDependency" class="mb-6">
              <label class="block text-gray-700 text-sm font-bold mb-2">
                مجموعة الحضور (اختياري)
              </label>
              <select
                v-model="form.attendance_group_id"
                class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option :value="null">بدون مجموعة</option>
                <option v-for="group in attendanceGroups" :key="group.id" :value="group.id">
                  {{ group.name }} (حد: {{ group.max_present }})
                </option>
              </select>
              <p class="text-xs text-gray-500 mt-1">يمكنك إنشاء مجموعة جديدة من صفحة "مجموعات الحضور".</p>
              <div v-if="errors.attendance_group_id" class="text-red-500 text-sm mt-1">{{ errors.attendance_group_id }}</div>
            </div>

            <!-- أزرار الإجراءات -->
            <div class="flex gap-4">
              <button
                type="submit"
                :disabled="loading"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg disabled:opacity-50 flex items-center gap-2"
              >
                <span v-if="loading">⏳</span>
                <span v-else>💾</span>
                {{ loading ? 'جاري الحفظ...' : 'حفظ التغييرات' }}
              </button>
              
              <Link
                :href="route('admin.employees.index')"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg flex items-center gap-2"
              >
                ❌ إلغاء
              </Link>
            </div>
          </form>

          <!-- معلومات إضافية -->
          <div class="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 class="font-semibold text-gray-800 mb-2">📊 معلومات إضافية:</h4>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="font-medium text-gray-600">تاريخ الإضافة:</span>
                <span class="text-gray-800">{{ formatDate(employee.created_at) }}</span>
              </div>
              <div>
                <span class="font-medium text-gray-600">آخر تحديث:</span>
                <span class="text-gray-800">{{ formatDate(employee.updated_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  components: {
    Link,
  },
  props: {
    employee: Object,
    errors: Object,
    employees: {
      type: Array,
      default: () => [],
    },
    canManageAttendanceDependency: {
      type: Boolean,
      default: false,
    },
    attendanceGroups: {
      type: Array,
      default: () => [],
    },
  },
  setup(props) {
    const form = useForm({
      name: props.employee.name,
      hourly_rate: props.employee.hourly_rate,
      phone: props.employee.phone || '',
      position: props.employee.position || '',
      notes: props.employee.notes || '',
      is_active: props.employee.is_active,
      attendance_dependency_employee_id: props.employee.attendance_dependency_employee_id || null,
      attendance_group_id: props.employee.attendance_group_id || null,
    });

    return { form };
  },
  computed: {
    loading() {
      return this.form.processing;
    },
  },
  methods: {
    submitForm() {
      this.form.put(route('admin.employees.update', this.employee.id));
    },
    formatDate(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
  },
};
</script> 