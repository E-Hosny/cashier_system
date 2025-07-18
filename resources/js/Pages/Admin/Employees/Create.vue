<template>
  <AppLayout title="إضافة موظف جديد">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        👥 إضافة موظف جديد
      </h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <!-- رأس الصفحة -->
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">معلومات الموظف الجديد</h3>
            <p class="text-sm text-gray-600">أدخل بيانات الموظف الجديد</p>
          </div>

          <!-- نموذج إضافة موظف -->
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

            <!-- أزرار الإجراءات -->
            <div class="flex gap-4">
              <button
                type="submit"
                :disabled="loading"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg disabled:opacity-50 flex items-center gap-2"
              >
                <span v-if="loading">⏳</span>
                <span v-else>💾</span>
                {{ loading ? 'جاري الحفظ...' : 'حفظ الموظف' }}
              </button>
              
              <Link
                :href="route('admin.employees.index')"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg flex items-center gap-2"
              >
                ❌ إلغاء
              </Link>
            </div>
          </form>

          <!-- ملاحظات -->
          <div class="mt-6 p-4 bg-blue-50 rounded-lg">
            <h4 class="font-semibold text-blue-800 mb-2">📋 ملاحظات:</h4>
            <ul class="text-sm text-blue-700 space-y-1">
              <li>• الحقول المطلوبة: اسم الموظف وسعر الساعة</li>
              <li>• سعر الساعة يجب أن يكون رقم موجب</li>
              <li>• يمكنك إضافة معلومات إضافية مثل رقم الهاتف والوظيفة</li>
              <li>• بعد الإضافة يمكنك تسجيل الحضور والانصراف للموظف</li>
            </ul>
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
  setup() {
    const form = useForm({
      name: '',
      hourly_rate: '',
      phone: '',
      position: '',
      notes: '',
    });

    return { form };
  },
  props: {
    errors: Object,
  },
  computed: {
    loading() {
      return this.form.processing;
    },
  },
  methods: {
    submitForm() {
      this.form.post(route('admin.employees.store'));
    },
  },
};
</script> 