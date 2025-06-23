<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800">🛢️ إدارة المواد الخام</h1>
      <a :href="route('admin.raw-materials.create')" class="btn-primary">➕ إضافة مادة خام</a>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-hidden">
      <table class="w-full text-end">
        <thead class="bg-gray-200">
          <tr>
            <th class="p-4">اسم المادة</th>
            <th class="p-4">الكمية الحالية (المخزون)</th>
            <th class="p-4">وحدة القياس</th>
            <th class="p-4">حد التنبيه</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="material in rawMaterials" :key="material.id" class="border-t hover:bg-gray-50" :class="{'bg-red-100 hover:bg-red-200': isStockLow(material)}">
            <td class="p-4">{{ material.name }}</td>
            <td class="p-4 font-mono font-bold">{{ material.stock }}</td>
            <td class="p-4">{{ material.unit }}</td>
            <td class="p-4">{{ material.stock_alert_threshold || 'لم يحدد' }}</td>
            <td class="p-4 text-center">
              <div class="flex justify-center items-center gap-2">
                <a :href="route('admin.raw-materials.edit', material.id)" class="btn-yellow">✏️ تعديل</a>
                <button @click="deleteMaterial(material.id)" class="btn-red">🗑️ حذف</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    rawMaterials: Array,
  },
  methods: {
    deleteMaterial(id) {
      if (confirm("هل أنت متأكد من حذف هذه المادة الخام؟")) {
        Inertia.delete(route("admin.raw-materials.destroy", id));
      }
    },
    isStockLow(material) {
        if (!material.stock_alert_threshold) return false;
        return parseFloat(material.stock) <= parseFloat(material.stock_alert_threshold);
    }
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold px-4 py-2 rounded-lg transition;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold px-4 py-2 rounded-lg transition;
}
</style> 