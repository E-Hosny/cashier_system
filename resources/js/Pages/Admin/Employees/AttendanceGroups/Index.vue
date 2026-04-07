<template>
  <AppLayout title="مجموعات الحضور">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">👥 مجموعات الحضور</h2>
    </template>

    <div class="py-12" dir="rtl">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-xl sm:rounded-lg p-6">
          <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900">إنشاء مجموعة جديدة</h3>
            <p class="text-sm text-gray-600">حدد اسم المجموعة والحد الأقصى للحضور المتزامن داخلها.</p>
          </div>

          <form @submit.prevent="createGroup" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-8">
            <input v-model="createForm.name" type="text" class="border rounded-lg p-3" placeholder="اسم المجموعة (مثال: فريق الصباح)" />
            <input v-model="createForm.max_present" type="number" min="1" max="20" class="border rounded-lg p-3" placeholder="الحد الأقصى (مثال: 2)" />
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-3">➕ إنشاء مجموعة</button>
          </form>

          <div class="overflow-x-auto">
            <table class="w-full text-right">
              <thead class="bg-gray-100">
                <tr>
                  <th class="p-3">اسم المجموعة</th>
                  <th class="p-3">الحد الأقصى</th>
                  <th class="p-3">عدد الموظفين</th>
                  <th class="p-3">الإجراءات</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="group in groups" :key="group.id" class="border-b">
                  <td class="p-3">
                    <input v-model="group.name" class="border rounded p-2 w-full" />
                  </td>
                  <td class="p-3">
                    <input v-model="group.max_present" type="number" min="1" max="20" class="border rounded p-2 w-24" />
                  </td>
                  <td class="p-3">{{ group.employees_count }}</td>
                  <td class="p-3 flex gap-2">
                    <button @click="updateGroup(group)" class="bg-green-600 hover:bg-green-700 text-white rounded px-3 py-2 text-sm">💾 حفظ</button>
                    <button @click="deleteGroup(group)" class="bg-red-600 hover:bg-red-700 text-white rounded px-3 py-2 text-sm">🗑️ حذف</button>
                  </td>
                </tr>
                <tr v-if="groups.length === 0">
                  <td colspan="4" class="p-4 text-center text-gray-500">لا توجد مجموعات حضور بعد.</td>
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
import { router, useForm } from '@inertiajs/vue3';

export default {
  layout: AppLayout,
  props: {
    groups: { type: Array, default: () => [] },
  },
  data() {
    return {
      createForm: useForm({
        name: '',
        max_present: 1,
      }),
    };
  },
  methods: {
    createGroup() {
      this.createForm.post(route('admin.employees.attendance-groups.store'));
    },
    updateGroup(group) {
      router.put(route('admin.employees.attendance-groups.update', group.id), {
        name: group.name,
        max_present: group.max_present,
      });
    },
    deleteGroup(group) {
      if (!confirm(`حذف مجموعة ${group.name}؟`)) return;
      router.delete(route('admin.employees.attendance-groups.destroy', group.id));
    },
  },
};
</script>

