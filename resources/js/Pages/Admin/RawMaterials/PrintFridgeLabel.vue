<template>
  <div class="sticker-print-page container mx-auto p-6" dir="rtl">
    <div class="no-print flex flex-wrap justify-between items-center gap-4 mb-8">
      <a :href="route('admin.raw-materials.index', { tab: 'fridge' })" class="btn-gray">⬅️ العودة للتلاجة</a>
      <button type="button" class="btn-primary" @click="doPrint">🖨️ طباعة</button>
    </div>

    <div class="no-print bg-white border rounded-xl p-8 max-w-md mx-auto text-center shadow mb-4">
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
        <svg ref="barcodeSvgPreview" class="max-w-full h-auto"></svg>
      </div>
      <p class="font-mono text-sm break-all text-gray-800">{{ label.label_code }}</p>
    </div>

    <div class="label-print-area print-only sticker-label" dir="rtl">
      <h2 class="sticker-title">{{ printTitle }}</h2>
      <p v-if="printSubtitle" class="sticker-qty sticker-meta">{{ printSubtitle }}</p>
      <div class="sticker-barcode-wrap">
        <svg ref="barcodeSvgPrint" class="sticker-barcode"></svg>
      </div>
      <p class="sticker-code sticker-code-text">{{ label.label_code }}</p>
    </div>
  </div>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { translateSize } from '@/utils/productSizes';
import { renderPreviewBarcode, renderPrintBarcode } from '@/utils/barcodeLabel';

export default {
  layout: AppLayout,
  props: {
    label: { type: Object, required: true },
    productName: { type: String, default: '' },
    lines: { type: Array, default: () => [] },
  },
  computed: {
    printTitle() {
      if (this.lines?.length > 1) {
        return `مجمّع (${this.lines.length})`;
      }
      return this.productName;
    },
    printSubtitle() {
      if (this.lines?.length > 1) {
        return null;
      }
      if (this.label.unit_count) {
        return `${this.label.unit_count} وحدة`;
      }
      return null;
    },
  },
  mounted() {
    this.renderBarcodes();
  },
  methods: {
    translateSize,
    renderBarcodes() {
      this.$nextTick(() => {
        if (!this.label?.label_code) return;
        renderPreviewBarcode(this.$refs.barcodeSvgPreview, this.label.label_code);
        renderPrintBarcode(this.$refs.barcodeSvgPrint, this.label.label_code);
      });
    },
    doPrint() {
      window.print();
    },
  },
};
</script>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg; }
.btn-gray { @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg; }
</style>
