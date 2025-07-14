<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">➕ إضافة مادة خام جديدة</h1>
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
            <label for="unit" class="block text-gray-700 mb-2">وحدة القياس (تظهر في المخزون)</label>
            <input id="unit" v-model="form.unit" type="text" class="input-style" placeholder="مثال: لتر، كجم، قطعة" required />
          </div>

          <div>
            <label for="stock" class="block text-gray-700 mb-2">الكمية الأولية في المخزون</label>
            <input id="stock" v-model="form.stock" type="number" step="0.01" class="input-style" required />
          </div>

          <div>
            <label for="stock_alert_threshold" class="block text-gray-700 mb-2">حد التنبيه (اختياري)</label>
            <input id="stock_alert_threshold" v-model="form.stock_alert_threshold" type="number" step="0.01" class="input-style" />
            <p class="text-sm text-gray-500 mt-1">سيتم تنبيهك عند وصول المخزون إلى هذا الحد.</p>
          </div>

        </div>

        <!-- قسم التسعير الذكي -->
        <div class="border-t pt-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">💰 معلومات الشراء والتسعير (بالجنيه)</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-gray-700 mb-2">وحدة الشراء</label>
              <select v-model="form.purchase_unit" class="input-style" required>
                <option value="">اختر وحدة الشراء</option>
                <option value="لتر">لتر</option>
                <option value="كجم">كجم</option>
                <option value="قطعة">قطعة</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-700 mb-2">الكمية المشتراة</label>
              <input v-model="form.purchase_quantity" type="number" step="0.001" class="input-style" placeholder="مثال: 1" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-2">سعر الشراء (بالجنيه)</label>
              <input v-model="form.purchase_price" type="number" step="0.01" class="input-style" placeholder="مثال: 100" required />
            </div>
            <div>
              <label class="block text-gray-700 mb-2">وحدة الاستهلاك</label>
              <select v-model="form.consume_unit" class="input-style" required>
                <option value="">اختر وحدة الاستهلاك</option>
                <option value="مللي">مللي</option>
                <option value="جرام">جرام</option>
                <option value="قطعة">قطعة</option>
              </select>
            </div>
          </div>
          <div class="mt-6">
            <div class="bg-gray-100 rounded-lg p-4">
              <span class="font-bold text-green-700">سعر وحدة الاستهلاك:</span>
              <span class="font-mono text-lg">{{ unitConsumePrice }} جنيه / {{ form.consume_unit || '-' }}</span>
              <span v-if="form.purchase_unit && form.consume_unit" class="text-xs text-gray-500 ml-2">(يحسب تلقائياً)</span>
            </div>
          </div>
        </div>

        <button type="submit" class="btn-primary w-full !mt-8">
          ➕ إضافة المادة الخام
        </button>
      </form>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import AppLayout from '@/Layouts/AppLayout.vue';

const conversionFactors = {
  'لتر': { 'مللي': 1000, 'لتر': 1 },
  'كجم': { 'جرام': 1000, 'كجم': 1 },
  'قطعة': { 'قطعة': 1 }
};

export default {
    layout: AppLayout,
    data() {
        return {
            form: {
                name: "",
                unit: "",
                stock: 0,
                stock_alert_threshold: null,
                purchase_unit: "",
                purchase_quantity: null,
                purchase_price: null,
                consume_unit: ""
            },
        };
    },
    computed: {
      unitConsumePrice() {
        const { purchase_unit, purchase_quantity, purchase_price, consume_unit } = this.form;
        if (!purchase_unit || !purchase_quantity || !purchase_price || !consume_unit) return 0;
        const factor = (conversionFactors[purchase_unit] && conversionFactors[purchase_unit][consume_unit]) || 1;
        const totalConsumeUnits = purchase_quantity * factor;
        if (!totalConsumeUnits) return 0;
        return (purchase_price / totalConsumeUnits).toFixed(4);
      }
    },
    methods: {
        submit() {
            const submitData = {
                ...this.form,
                unit_consume_price: this.unitConsumePrice
            };
            Inertia.post(route("admin.raw-materials.store"), submitData);
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
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-red {
  @apply bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style> 