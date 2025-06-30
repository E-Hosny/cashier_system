<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">🛢️ إدارة المواد الخام</h1>
      <a :href="route('admin.raw-materials.create')" class="btn-primary">➕ إضافة مادة خام</a>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-x-auto">
      <table class="w-full text-end">
        <thead class="bg-gray-200 hidden sm:table-header-group">
          <tr>
            <th class="p-4">اسم المادة</th>
            <th class="p-4">الكمية الحالية (المخزون)</th>
            <th class="p-4">وحدة القياس</th>
            <th class="p-4">حد التنبيه</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="material in rawMaterials" :key="material.id" class="block sm:table-row border-t sm:border-t-0 border-gray-200 hover:bg-gray-50" :class="{'bg-red-100 hover:bg-red-200': isStockLow(material)}">
            <td class="p-4 block sm:table-cell" data-label="اسم المادة">{{ material.name }}</td>
            <td class="p-4 block sm:table-cell font-mono font-bold" data-label="الكمية الحالية (المخزون)">{{ material.stock }}</td>
            <td class="p-4 block sm:table-cell" data-label="وحدة القياس">{{ material.unit }}</td>
            <td class="p-4 block sm:table-cell" data-label="حد التنبيه">{{ material.stock_alert_threshold || 'لم يحدد' }}</td>
            <td class="p-4 block sm:table-cell" data-label="الإجراءات">
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

/* Styles for responsive table */
@media (max-width: 640px) {
  td[data-label]::before {
    content: attr(data-label) " :";
    font-weight: bold;
    display: inline-block;
    margin-right: 0.5rem; /* Equivalent to mr-2 in Tailwind */
    min-width: 140px; /* Adjust as needed */
    text-align: right;
  }

  td.p-4 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e5e7eb; /* gray-200 */
  }
  
  td > * {
    flex-grow: 1;
    text-align: left;
  }
  
  td > .flex {
      justify-content: flex-end;
  }

  tr.block:last-child td:last-child {
    border-bottom: none;
  }
}
</style> 