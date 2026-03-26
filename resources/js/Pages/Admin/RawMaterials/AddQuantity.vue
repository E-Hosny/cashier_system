<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-gray-800">➕ إضافة كمية للمادة الخام</h1>
      <a :href="route('admin.raw-materials.index')" class="btn-gray">⬅️ رجوع لقائمة المواد الخام</a>
    </div>

    <div class="bg-white shadow-md rounded-xl p-6 border max-w-xl">
      <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-2">{{ rawMaterial.name }}</h2>
        <p class="text-sm text-gray-600">
          المخزون الحالي:
          <span class="font-mono font-bold">{{ formattedStock }}</span>
          {{ rawMaterial.consume_unit }}
          <span v-if="rawMaterial.quantity_per_unit" class="text-gray-500">
            (≈ {{ currentUnits }} {{ rawMaterial.unit }})
          </span>
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <div>
          <label for="quantity_units" class="block text-gray-700 font-medium mb-2">
            عدد الوحدات التي تريد إضافتها ({{ rawMaterial.unit || 'وحدة' }})
          </label>
          <input
            id="quantity_units"
            v-model.number="form.quantity_units"
            type="number"
            step="any"
            min="0.001"
            class="input-style"
            placeholder="مثال: 3 (قطع / كراتين)"
            required
          />
          <p v-if="rawMaterial.quantity_per_unit" class="text-sm text-gray-500 mt-1">
            كل {{ rawMaterial.unit }} = {{ rawMaterial.quantity_per_unit }} {{ rawMaterial.consume_unit }}.
            سيتم إضافة إجمالي
            <span class="font-semibold text-green-700">{{ totalToAdd }}</span>
            {{ rawMaterial.consume_unit }} إلى المخزون.
          </p>
        </div>

        <div>
          <label for="note" class="block text-gray-700 font-medium mb-2">ملاحظة (اختياري)</label>
          <input
            id="note"
            v-model="form.note"
            type="text"
            class="input-style"
            placeholder="مثال: شراء إضافي بدون فاتورة"
          />
        </div>

        <button type="submit" class="btn-primary w-full mt-4">
          💾 حفظ الكمية المضافة
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
  props: {
    rawMaterial: Object,
  },
  data() {
    return {
      form: {
        quantity_units: null,
        note: '',
      },
    };
  },
  computed: {
    formattedStock() {
      const s = parseFloat(this.rawMaterial.stock) || 0;
      return s % 1 === 0 ? s : s.toFixed(2);
    },
    currentUnits() {
      if (!this.rawMaterial.quantity_per_unit) return '-';
      const u =
        (parseFloat(this.rawMaterial.stock) || 0) /
        (parseFloat(this.rawMaterial.quantity_per_unit) || 1);
      return u % 1 === 0 ? u : u.toFixed(2);
    },
    totalToAdd() {
      if (!this.rawMaterial.quantity_per_unit || !this.form.quantity_units) return 0;
      const perUnit = parseFloat(this.rawMaterial.quantity_per_unit) || 0;
      const units = parseFloat(this.form.quantity_units) || 0;
      const total = perUnit * units;
      return total % 1 === 0 ? total : total.toFixed(2);
    },
  },
  methods: {
    submit() {
      Inertia.post(
        route('admin.raw-materials.add-quantity.store', this.rawMaterial.id),
        this.form
      );
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

