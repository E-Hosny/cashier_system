<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="no-print flex flex-wrap justify-between items-center gap-4 mb-8">
      <a :href="route('admin.raw-materials.index', { tab: 'fridge' })" class="btn-gray">⬅️ العودة للتلاجة</a>
      <button type="button" class="btn-primary" @click="doPrint">🖨️ طباعة</button>
    </div>

    <div class="label-print-area bg-white border rounded-xl p-8 max-w-md mx-auto text-center shadow">
      <h2 class="text-xl font-bold text-gray-900 mb-2">{{ productName }}</h2>

      <ul v-if="lines && lines.length > 1" class="text-right text-sm mb-4 space-y-2 border rounded-lg p-3">
        <li v-for="(row, i) in lines" :key="i" class="flex justify-between gap-2 border-b pb-1 last:border-0">
          <span>
            {{ row.product_name }}
            <span v-if="row.size" class="text-gray-500">({{ translateSize(row.size) }})</span>
          </span>
          <span class="font-bold">{{ row.unit_count }}</span>
        </li>
      </ul>
      <template v-else>
        <p v-if="label.size" class="text-gray-600 mb-2">المقاس: {{ translateSize(label.size) }}</p>
        <p v-if="label.unit_count" class="text-gray-700 mb-4">
          <span class="font-semibold">{{ label.unit_count }}</span> وحدة
        </p>
      </template>

      <div class="flex justify-center mb-3">
        <svg ref="barcodeSvg" class="max-w-full h-auto"></svg>
      </div>
      <p class="font-mono text-sm break-all text-gray-800 mb-4">{{ label.label_code }}</p>
      <p v-if="label.status === 'pending'" class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 text-sm">
        {{ lines && lines.length > 1 ? 'كود مجمّع — مسح واحد في الفرع يُدخل كل المنتجات للتلاجة.' : 'بانتظار مسح الفرع.' }}
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
    lines: { type: Array, default: () => [] },
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
            width: 1.2,
            height: 52,
            displayValue: false,
            margin: 10,
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
  @page {
    size: 58mm 40mm;
    margin: 0;
  }
  body {
    margin: 0 !important;
    padding: 0 !important;
  }
  .no-print {
    display: none !important;
  }
  .label-print-area {
    width: 58mm !important;
    height: 40mm !important;
    padding: 2mm !important;
    margin: 0 auto !important;
    box-shadow: none !important;
    border: 0.2mm dashed #d1d5db !important;
    border-radius: 0 !important;
    max-width: 58mm !important;
    display: flex !important;
    flex-direction: column;
    justify-content: center;
    gap: 1mm;
    page-break-inside: avoid;
    overflow: hidden;
  }
  .label-print-area h2 {
    font-size: 11px !important;
    line-height: 1.2 !important;
    margin: 0 !important;
  }
  .label-print-area p {
    margin: 0 !important;
    line-height: 1.2 !important;
    font-size: 9px !important;
  }
  .label-print-area svg {
    width: 52mm !important;
    height: 14mm !important;
  }
}
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg; }
.btn-gray { @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg; }
</style>
