<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4 no-print">
      <h1 class="text-3xl font-bold text-gray-800">🛢️ إدارة المواد الخام</h1>
      <div class="flex flex-wrap gap-2">
        <a v-if="canReceive" :href="route('admin.raw-materials.pending-receive')" class="btn-blue">📥 سحب المنتجات</a>
        <a v-if="canAddRaw" :href="route('admin.raw-materials.create')" class="btn-primary">➕ إضافة مادة خام</a>
      </div>
    </div>

    <div class="bg-white shadow-lg rounded-xl overflow-x-auto no-print">
      <table class="w-full text-end">
        <thead class="bg-gray-200 hidden sm:table-header-group">
          <tr>
            <th class="p-4">اسم المادة</th>
            <th class="p-4">الكمية الحالية (المخزون)</th>
            <th class="p-4">وحدة القياس</th>
            <th class="p-4">معلومات التسعير</th>
            <th class="p-4">حد التنبيه</th>
            <th class="p-4 text-center">الإجراءات</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="material in rawMaterialsLocal" :key="material.id" class="block sm:table-row border-t sm:border-t-0 border-gray-200 hover:bg-gray-50" :class="{'bg-red-100 hover:bg-red-200': isStockLow(material)}">
            <td class="p-4 block sm:table-cell" data-label="اسم المادة">{{ material.name }}</td>
            <td class="p-4 block sm:table-cell font-mono font-bold" data-label="الكمية الحالية (المخزون)">
              <template v-if="material.quantity_per_unit">
                {{ formatStockUnits(material) }} {{ material.unit }}
                <span class="text-gray-600 font-normal">({{ formatStockConsume(material) }} {{ material.consume_unit }})</span>
                <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                  قيد الاستلام: {{ formatPendingPieces(material) }} {{ material.unit }}
                </span>
              </template>
              <template v-else>
                {{ material.stock }} {{ material.consume_unit }}
                <span v-if="material.purchase_unit && material.consume_unit && material.stock" class="text-gray-600 font-normal">
                  ({{ (material.stock / ((material.purchase_unit === 'لتر' && material.consume_unit === 'مللي') ? 1000 : (material.purchase_unit === 'كجم' && material.consume_unit === 'جرام') ? 1000 : 1)).toFixed(2) }} {{ material.purchase_unit }})
                </span>
                <span v-if="material.pending_pieces > 0" class="block text-amber-800 text-sm font-semibold mt-1">
                  قيد الاستلام: {{ formatPendingPieces(material) }} {{ material.unit }}
                </span>
              </template>
            </td>
            <td class="p-4 block sm:table-cell" data-label="وحدة القياس">{{ material.unit }}</td>
            <td class="p-4 block sm:table-cell" data-label="معلومات التسعير">
              <div v-if="material.unit_consume_price" class="text-sm">
                <div class="font-semibold text-green-700">{{ material.unit_consume_price }} جنيه / {{ material.consume_unit }}</div>
                <div class="text-xs text-gray-600 mt-1">سعر وحدة الاستهلاك محسوب تلقائياً</div>
              </div>
              <div v-else class="text-gray-500 text-sm">لم يتم تحديد السعر</div>
            </td>
            <td class="p-4 block sm:table-cell" data-label="حد التنبيه">{{ formatAlertThreshold(material) }}</td>
            <td class="p-4 block sm:table-cell" data-label="الإجراءات">
              <div class="flex flex-wrap justify-center items-center gap-2">
                <button v-if="canPrint" type="button" @click="openPrintModal(material)" class="btn-blue-outline">🏷️ طباعة كود</button>
                <a v-if="canEdit" :href="route('admin.raw-materials.edit', material.id)" class="btn-yellow">✏️ تعديل</a>
                <button v-if="canDelete" @click="deleteMaterial(material.id)" class="btn-red">🗑️ حذف</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="printModal.open"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="closePrintModal"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 print-only" dir="rtl">
        <h3 class="text-lg font-bold text-gray-800 mb-2">طباعة كود — {{ printModal.materialName }}</h3>

        <p v-if="!printModal.created" class="text-sm text-gray-600 mb-4">
          أدخل عدد القطع المراد تكويدها (لن يُضاف للمخزون حتى «سحب المنتجات»).
        </p>

        <p v-else class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg py-2 px-3 mb-4">
          تم تكويد {{ printModal.piece_count }} قطعة كمُعلّق لم تصل للمحل بعد.
        </p>

        <div v-if="!printModal.created">
          <label class="block text-gray-700 text-sm mb-1">عدد القطع</label>
          <input
            v-model.number="printModal.piece_count"
            type="number"
            step="any"
            min="0.001"
            class="w-full border rounded-lg p-3 mb-4"
          />
        </div>

        <div v-else>
          <p class="text-sm text-gray-700 mb-2">
            الكود: <span class="font-mono break-all">{{ printModal.label_code }}</span>
          </p>
          <div class="flex justify-center mb-3">
            <svg ref="barcodeSvgPreview" class="max-w-full h-auto"></svg>
          </div>
          <p class="text-xs text-gray-600 mb-4">
            سيتم إضافة الكمية للمخزون عند سحب المنتجات عبر نفس الكود.
          </p>
        </div>

        <div class="flex gap-2 justify-end">
          <button type="button" class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300" @click="closePrintModal">
            إغلاق
          </button>
          <button
            v-if="!printModal.created"
            type="button"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
            @click="submitPrintLabel"
          >
            متابعة للطباعة
          </button>
          <button
            v-else
            type="button"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"
            @click="doPrint"
          >
            طباعة
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Inertia } from "@inertiajs/inertia";
import JsBarcode from 'jsbarcode';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    rawMaterials: Array,
  },
  computed: {
    userRoles() {
      return this.$page?.props?.auth?.user?.roles || [];
    },
    isSuperAdmin() {
      return this.userRoles.includes('super admin');
    },
    isAdmin() {
      return this.userRoles.includes('admin');
    },
    isCashier() {
      return this.userRoles.includes('cashier');
    },
    canReceive() {
      return this.isCashier || this.isAdmin || this.isSuperAdmin;
    },
    canAddRaw() {
      return this.isSuperAdmin;
    },
    canPrint() {
      return this.isAdmin || this.isSuperAdmin;
    },
    canEdit() {
      return this.isAdmin || this.isSuperAdmin;
    },
    canDelete() {
      return this.isSuperAdmin;
    },
  },
  data() {
    return {
      rawMaterialsLocal: this.rawMaterials,
      printModal: {
        open: false,
        materialId: null,
        materialName: '',
        piece_count: 1,
        created: false,
        label_code: '',
      },
    };
  },
  watch: {
    rawMaterials: {
      deep: true,
      handler(val) {
        this.rawMaterialsLocal = val;
      },
    },
  },
  methods: {
    openPrintModal(material) {
      this.printModal = {
        open: true,
        materialId: material.id,
        materialName: material.name,
        piece_count: 1,
        created: false,
        label_code: '',
      };
    },
    closePrintModal() {
      this.printModal.open = false;
    },
    doPrint() {
      window.print();
    },
    submitPrintLabel() {
      const n = parseFloat(this.printModal.piece_count);
      if (!this.printModal.materialId || !n || n < 0.001) {
        alert('أدخل عدد قطع صالحاً.');
        return;
      }

      // AJAX call to avoid redirect to another page (easy UX for testing).
      window.axios
        .post(route('admin.raw-materials.labels.store', this.printModal.materialId), {
          piece_count: n,
        })
        .then((res) => {
          const d = res.data || {};
          this.printModal.created = true;
          this.printModal.label_code = d.label_code || '';
          this.printModal.consume_amount = d.consume_amount || null;

          // Update pending pieces immediately on the list.
          const m = this.rawMaterialsLocal.find((x) => x.id === this.printModal.materialId);
          if (m) {
            m.pending_pieces = (parseFloat(m.pending_pieces) || 0) + n;
          }

          this.$nextTick(() => {
            const el = this.$refs.barcodeSvgPreview;
            if (el && this.printModal.label_code) {
              JsBarcode(el, this.printModal.label_code, {
                format: 'CODE128',
                width: 2,
                height: 72,
                displayValue: false,
                margin: 8,
              });
            }
          });
        })
        .catch((err) => {
          const msg = err?.response?.data?.message || 'حدث خطأ أثناء تكويد الباركود.';
          alert(msg);
        });
    },
    deleteMaterial(id) {
      if (confirm("هل أنت متأكد من حذف هذه المادة الخام؟")) {
        Inertia.delete(route("admin.raw-materials.destroy", id));
      }
    },
    isStockLow(material) {
        if (!material.stock_alert_threshold) return false;
        return parseFloat(material.stock) <= parseFloat(material.stock_alert_threshold);
    },
    formatStockUnits(material) {
      if (!material.quantity_per_unit) return material.stock;
      const u = parseFloat(material.stock) / parseFloat(material.quantity_per_unit);
      return u % 1 === 0 ? u : parseFloat(u).toFixed(2);
    },
    formatStockConsume(material) {
      const s = parseFloat(material.stock);
      return s % 1 === 0 ? s : s.toFixed(2);
    },
    formatPendingPieces(material) {
      const p = parseFloat(material.pending_pieces);
      if (Number.isNaN(p)) return '0';
      return p % 1 === 0 ? p : p.toFixed(2);
    },
    formatAlertThreshold(material) {
      if (material.stock_alert_threshold == null || material.stock_alert_threshold === '') return 'لم يحدد';
      const t = parseFloat(material.stock_alert_threshold);
      if (material.quantity_per_unit) {
        const qpu = parseFloat(material.quantity_per_unit);
        const units = t / qpu;
        const u = units % 1 === 0 ? units : parseFloat(units).toFixed(2);
        return u + ' ' + (material.unit || 'قطعة');
      }
      return t + ' ' + (material.consume_unit || '');
    },
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-blue {
  @apply bg-sky-600 hover:bg-sky-700 text-white font-bold py-2 px-4 rounded-lg transition shadow-md;
}
.btn-blue-outline {
  @apply border-2 border-sky-600 text-sky-700 hover:bg-sky-50 font-bold py-2 px-4 rounded-lg transition;
}
.btn-yellow {
  @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-red {
  @apply bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition;
}

@media print {
  .no-print {
    display: none !important;
  }
  .print-only {
    display: block !important;
    box-shadow: none !important;
    border: none !important;
  }
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