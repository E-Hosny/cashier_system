<template>
  <div class="container mx-auto p-6" dir="rtl">
    <div class="no-print flex flex-wrap justify-between items-center gap-4 mb-8">
      <a :href="route('admin.raw-materials.index')" class="btn-gray">⬅️ العودة للقائمة</a>
      <button type="button" class="btn-primary" @click="doPrint">🖨️ طباعة</button>
    </div>

    <div class="label-print-area bg-white border rounded-xl p-8 max-w-md mx-auto text-center shadow">
      <h2 class="text-xl font-bold text-gray-900 mb-2">{{ productName }}</h2>
      <p class="text-gray-700 mb-1">
        <span class="font-semibold">{{ label.piece_count }}</span> {{ unit || 'قطعة' }}
      </p>
      <p class="text-sm text-gray-600 mb-4">
        ≈ {{ formatNum(label.consume_amount) }} {{ consumeUnit }}
      </p>
      <div class="flex justify-center mb-2">
        <svg ref="barcodeSvg" class="max-w-full h-auto"></svg>
      </div>
      <p class="font-mono text-xs break-all text-gray-800 mb-2">{{ label.label_code }}</p>
      <p v-if="label.status === 'pending'" class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 text-sm">
        تم تكويد {{ label.piece_count }} {{ unit || 'قطعة' }} — بانتظار سحب الفرع عبر الباركود.
      </p>
    </div>
  </div>
</template>

<script>
import JsBarcode from 'jsbarcode';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    label: {
      type: Object,
      required: true,
    },
    productName: { type: String, default: '' },
    unit: { type: String, default: '' },
    consumeUnit: { type: String, default: '' },
  },
  mounted() {
    this.renderBarcode();
  },
  updated() {
    this.renderBarcode();
  },
  methods: {
    formatNum(n) {
      const x = parseFloat(n);
      if (Number.isNaN(x)) return n;
      return x % 1 === 0 ? x : x.toFixed(2);
    },
    renderBarcode() {
      this.$nextTick(() => {
        const el = this.$refs.barcodeSvg;
        if (!el || !this.label?.label_code) return;
        try {
          while (el.firstChild) {
            el.removeChild(el.firstChild);
          }
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
.input-style {
  @apply w-full p-3 border border-gray-300 rounded-lg;
}
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-gray {
  @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
</style>
