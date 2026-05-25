<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="no-print flex flex-wrap justify-between items-center gap-4 mb-8">
      <a :href="route('admin.raw-materials.index', { tab: 'fridge' })" class="btn-gray">⬅️ العودة للتلاجة</a>
      <button type="button" class="btn-primary" @click="doPrint">🖨️ طباعة</button>
    </div>

    <div class="label-print-area bg-white border rounded-xl p-8 max-w-md mx-auto text-center shadow">
      <h2 class="text-xl font-bold text-gray-900 mb-2">{{ productName }}</h2>
      <p v-if="label.size" class="text-gray-600 mb-2">المقاس: {{ translateSize(label.size) }}</p>
      <p class="text-gray-700 mb-4">
        <span class="font-semibold">{{ label.unit_count }}</span> وحدة
      </p>
      <div class="flex justify-center mb-3">
        <svg ref="barcodeSvg" class="max-w-full h-auto"></svg>
      </div>
      <p class="font-mono text-sm break-all text-gray-800 mb-4">{{ label.label_code }}</p>
      <p v-if="label.status === 'pending'" class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 text-sm">
        تم التكويد — بانتظار مسح الفرع (إضافة للتلاجة بدون خصم مقادير).
      </p>
    </div>
  </div>
</template>

<script>
import JsBarcode from 'jsbarcode';
import AppLayout from '@/Layouts/AppLayout.vue';
import { translateSize } from '@/utils/productSizes';

export default {
  layout: AppLayout,
  props: {
    label: { type: Object, required: true },
    productName: { type: String, default: '' },
  },
  mounted() {
    this.renderBarcode();
  },
  methods: {
    translateSize,
    renderBarcode() {
      this.$nextTick(() => {
        const el = this.$refs.barcodeSvg;
        if (!el || !this.label?.label_code) return;
        try {
          while (el.firstChild) el.removeChild(el.firstChild);
          JsBarcode(el, this.label.label_code, {
            format: 'CODE128',
            width: 2,
            height: 72,
            displayValue: false,
            margin: 8,
          });
        } catch (e) {
          console.error(e);
        }
      });
    },
    doPrint() {
      window.print();
    },
  },
};
</script>

<style scoped>
@media print {
  .no-print { display: none !important; }
}
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg; }
.btn-gray { @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg; }
</style>
