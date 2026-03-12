<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800">➕ إضافة مادة خام جديدة</h1>
      <a :href="route('admin.raw-materials.index')" class="btn-gray">➡️ العودة إلى القائمة</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 border max-w-2xl">
      <form @submit.prevent="submit" class="space-y-6">
        <!-- 1. اسم المادة الخام -->
        <div>
          <label for="name" class="block text-gray-700 font-medium mb-2">١ – اسم المادة الخام</label>
          <input id="name" v-model="form.name" type="text" class="input-style" placeholder="مثال: صوص توت، كرتونة أكواب" required />
        </div>

        <!-- 2. سعر القطعة الواحدة -->
        <div>
          <label for="price_per_piece" class="block text-gray-700 font-medium mb-2">٢ – سعر القطعة الواحدة (بالجنيه)</label>
          <input id="price_per_piece" v-model.number="form.price_per_piece" type="number" step="0.01" min="0" class="input-style" placeholder="مثال: 75" required />
        </div>

        <!-- 3. وحدة الاستهلاك -->
        <div>
          <label for="consume_unit" class="block text-gray-700 font-medium mb-2">٣ – وحدة الاستهلاك</label>
          <select id="consume_unit" v-model="form.consume_unit" class="input-style" required>
            <option value="">اختر وحدة الاستهلاك</option>
            <option value="مللي">مللي</option>
            <option value="جرام">جرام</option>
            <option value="كوب">كوب</option>
            <option value="قطعة">قطعة</option>
          </select>
        </div>

        <!-- 4. عدد وحدات القطعة -->
        <div>
          <label for="quantity_per_unit" class="block text-gray-700 font-medium mb-2">٤ – عدد وحدات القطعة ({{ form.consume_unit || '—' }} في كل قطعة)</label>
          <input id="quantity_per_unit" v-model.number="form.quantity_per_unit" type="number" step="any" min="0.001" class="input-style" placeholder="مثال: 750 إذا القطعة = 750 مللي" required />
          <p class="text-sm text-gray-500 mt-1">القطعة الواحدة = هذا العدد من {{ form.consume_unit || 'وحدة الاستهلاك' }}</p>
        </div>

        <!-- سعر وحدة الاستهلاك (محسوب) -->
        <div v-if="form.price_per_piece && form.quantity_per_unit" class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
          <span class="font-bold text-emerald-800">سعر وحدة الاستهلاك (محسوب تلقائياً):</span>
          <span class="font-mono text-lg text-emerald-700 mr-2">{{ unitConsumePrice }} جنيه / {{ form.consume_unit }}</span>
        </div>

        <!-- 5. عدد القطع في المخزون -->
        <div>
          <label for="stock_units" class="block text-gray-700 font-medium mb-2">٥ – عدد القطع في المخزون</label>
          <input id="stock_units" v-model.number="form.stock_units" type="number" step="0.01" min="0" class="input-style" placeholder="مثال: 3" required />
          <p class="text-sm font-semibold text-green-700 mt-2">الإجمالي المتاح: {{ stockInConsumeUnit }} {{ form.consume_unit }}</p>
        </div>

        <!-- 6. وحدة القياس (للعرض في المخزون) -->
        <div>
          <label for="unit" class="block text-gray-700 font-medium mb-2">٦ – وحدة القياس (تظهر في المخزون)</label>
          <input id="unit" v-model="form.unit" type="text" class="input-style" placeholder="مثال: قطعة، كرتونة" required />
        </div>

        <!-- 7. حد التنبيه -->
        <div>
          <label for="stock_alert_threshold" class="block text-gray-700 font-medium mb-2">٧ – حد التنبيه (اختياري)</label>
          <input id="stock_alert_threshold" v-model="form.stock_alert_threshold" type="number" step="0.01" min="0" class="input-style" placeholder="بوحدة الاستهلاك (مثلاً 1500 مللي)" />
          <p class="text-sm text-gray-500 mt-1">سيتم تنبيهك عند وصول المخزون إلى هذا الحد.</p>
        </div>

        <button type="submit" class="btn-primary w-full !mt-8">
          ➕ إضافة المادة الخام
        </button>
      </form>
    </div>
  </div>
</template>

<script>
import { Inertia } from '@inertiajs/inertia';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  data() {
    return {
      form: {
        name: '',
        price_per_piece: null,
        consume_unit: '',
        quantity_per_unit: null,
        stock_units: 0,
        unit: 'قطعة',
        stock_alert_threshold: null,
      },
    };
  },
  computed: {
    stockInConsumeUnit() {
      if (!this.form.quantity_per_unit || this.form.stock_units == null) return '0';
      const total = (parseFloat(this.form.stock_units) || 0) * (parseFloat(this.form.quantity_per_unit) || 0);
      return total % 1 === 0 ? total : total.toFixed(2);
    },
    unitConsumePrice() {
      const p = parseFloat(this.form.price_per_piece);
      const q = parseFloat(this.form.quantity_per_unit);
      if (!p || !q) return '0.0000';
      return (p / q).toFixed(4);
    },
  },
  methods: {
    submit() {
      const stock = (parseFloat(this.form.stock_units) || 0) * (parseFloat(this.form.quantity_per_unit) || 0);
      const unitConsumePrice = (parseFloat(this.form.price_per_piece) || 0) / (parseFloat(this.form.quantity_per_unit) || 1);
      const submitData = {
        name: this.form.name,
        unit: this.form.unit,
        price_per_piece: this.form.price_per_piece,
        consume_unit: this.form.consume_unit,
        quantity_per_unit: this.form.quantity_per_unit,
        stock,
        stock_alert_threshold: this.form.stock_alert_threshold || null,
        unit_consume_price: unitConsumePrice,
      };
      Inertia.post(route('admin.raw-materials.store'), submitData);
    },
  },
};
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
