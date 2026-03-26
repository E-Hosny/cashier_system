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
      <div class="flex justify-center mb-3">
        <svg ref="barcodeSvg" class="max-w-full h-auto"></svg>
      </div>
      <p class="font-mono text-sm break-all text-gray-800 mb-4">{{ label.label_code }}</p>
      <p v-if="label.status === 'pending'" class="text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 text-sm">
        قيد الاستلام — تم تكويد هذه الكمية ولم تُضف للمخزون حتى يتم السحب من صفحة «سحب المنتجات».
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
  .no-print {
    display: none !important;
  }
  .label-print-area {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
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
