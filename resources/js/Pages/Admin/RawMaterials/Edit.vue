<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">🔄 تعديل المادة الخام</h1>
        <a :href="route('admin.raw-materials.index')" class="btn-gray">➡️ العودة إلى القائمة</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 border">
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          
          <div>
            <label for="name" class="block text-gray-700 mb-2">اسم المادة الخام</label>
            <input id="name" v-model="form.name" type="text" class="input-style" required />
          </div>

          <div>
            <label for="unit" class="block text-gray-700 mb-2">وحدة القياس (مثال: كجم، لتر، قطعة)</label>
            <input id="unit" v-model="form.unit" type="text" class="input-style" required />
          </div>

          <div>
            <label for="stock" class="block text-gray-700 mb-2">الكمية الحالية في المخزون</label>
            <input id="stock" v-model="form.stock" type="number" step="0.01" class="input-style" required />
          </div>

          <div>
            <label for="stock_alert_threshold" class="block text-gray-700 mb-2">حد التنبيه (اختياري)</label>
            <input id="stock_alert_threshold" v-model="form.stock_alert_threshold" type="number" step="0.01" class="input-style" />
            <p class="text-sm text-gray-500 mt-1">سيتم تنبيهك عند وصول المخزون إلى هذا الحد.</p>
          </div>

        </div>

        <button type="submit" class="btn-primary w-full !mt-8">
          💾 حفظ التعديلات
        </button>
      </form>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
    props: {
        rawMaterial: Object,
    },
    data() {
        return {
            form: { ...this.rawMaterial },
        };
    },
    methods: {
        submit() {
            Inertia.put(route("admin.raw-materials.update", this.rawMaterial.id), this.form);
        }
    }
}
</script>

<style scoped>
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg focus:ring focus:ring-blue-300 transition-all;
}
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-md;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style> 