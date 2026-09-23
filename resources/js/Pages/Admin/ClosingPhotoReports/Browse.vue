<template>
  <AppLayout title="عرض صور التقفيلة">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">عرض صور التقفيلة</h2>
    </template>

    <div class="py-10" dir="rtl">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">مراجعة صور التقفيلة</h3>
              <p class="text-sm text-gray-600">
                عرض فقط — يظهر كل البنود وحالة كل بند (مرفوع / متبقي) وجرد التلاجة لكل فرع.
              </p>
            </div>
            <span
              class="px-3 py-1 rounded-full text-xs font-semibold self-start"
              :class="enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
            >
              {{ enabled ? 'الخاصية مفعّلة' : 'الخاصية غير مفعّلة' }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
              <label class="block text-sm text-gray-700 mb-1">اليوم التشغيلي</label>
              <input v-model="localFilters.business_date" type="date" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters" />
            </div>
            <div v-if="canFilterAllBranches">
              <label class="block text-sm text-gray-700 mb-1">الفرع</label>
              <select v-model="localFilters.branch_id" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters">
                <option value="">كل الفروع</option>
                <option v-for="b in branches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm text-gray-700 mb-1">نوع التقفيلة</label>
              <select v-model="localFilters.closing_type" class="w-full rounded-lg border-gray-300 text-sm" @change="applyFilters">
                <option value="">الكل</option>
                <option value="evening">{{ typeLabels.evening }}</option>
                <option value="dawn">{{ typeLabels.dawn }}</option>
              </select>
            </div>
            <button
              type="button"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm"
              @click="applyFilters"
            >
              تحديث
            </button>
          </div>
        </div>

        <div v-if="!submissions.length" class="bg-white shadow-xl sm:rounded-lg p-10 text-center text-gray-500">
          لا توجد بنود تقفيلة مطابقة للفلتر.
        </div>

        <div v-for="row in submissions" :key="row.id" class="bg-white shadow-xl sm:rounded-lg p-5 space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1 min-w-0">
              <div class="font-semibold text-gray-900 text-lg">
                {{ row.branch_name }} · {{ row.closing_type_label }}
              </div>
              <div class="text-sm text-gray-600">
                اليوم التشغيلي: {{ row.business_date }}
                · بواسطة: {{ row.submitted_by_name || '—' }}
              </div>
              <div class="text-sm font-medium" :class="row.is_complete ? 'text-green-700' : 'text-amber-800'">
                {{ row.status_detail }}
              </div>
              <div v-if="row.completed_at" class="text-xs text-gray-500">اكتمل: {{ row.completed_at }}</div>
            </div>
            <div class="flex flex-col items-end gap-2">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="statusBadgeClass(row)"
              >
                {{ row.status_label }}
              </span>
              <span class="text-xs text-gray-600">
                إجباري: {{ row.required_uploaded }}/{{ row.required_total }}
                <span v-if="row.required_remaining > 0" class="text-amber-700">
                  · متبقي {{ row.required_remaining }}
                </span>
              </span>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div
              v-for="item in row.items"
              :key="`${row.id}-${item.id}`"
              class="border rounded-xl overflow-hidden"
              :class="item.is_uploaded ? 'border-green-200 bg-green-50/40' : 'border-amber-200 bg-amber-50/30'"
            >
              <button
                v-if="item.photo"
                type="button"
                class="block w-full text-right"
                @click="openLightbox(row, item.id)"
              >
                <img :src="item.photo.url" :alt="item.title" class="w-full h-36 object-cover" />
              </button>
              <div
                v-else
                class="h-36 flex items-center justify-center text-amber-800 text-sm font-medium bg-amber-50"
              >
                لم يُرفع بعد
              </div>
              <div class="p-3 space-y-1">
                <div class="flex items-start justify-between gap-2">
                  <div class="text-sm font-semibold text-gray-900">
                    {{ item.title }}
                    <span v-if="item.is_required" class="text-red-600 text-[11px]">* إجباري</span>
                    <span v-else class="text-gray-500 text-[11px]">اختياري</span>
                  </div>
                  <span
                    class="shrink-0 text-[11px] px-2 py-0.5 rounded-full font-semibold"
                    :class="item.is_uploaded ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
                  >
                    {{ item.is_uploaded ? 'تم الإرسال' : 'متبقي' }}
                  </span>
                </div>
                <div v-if="item.description" class="text-xs text-gray-500">{{ item.description }}</div>
                <div v-if="item.photo" class="text-[11px] text-gray-500">
                  {{ item.photo.uploaded_at || '—' }}
                </div>
              </div>
            </div>
          </div>

          <div
            v-if="row.fridge?.required"
            class="rounded-xl border p-4 space-y-3"
            :class="row.fridge.counted ? 'border-cyan-200 bg-cyan-50/60' : 'border-amber-200 bg-amber-50/40'"
          >
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <div class="font-semibold text-gray-900">جرد التلاجة</div>
                <div class="text-xs text-gray-600" v-if="row.fridge.counted_at">
                  تم الجرد: {{ row.fridge.counted_at }}
                </div>
              </div>
              <span
                class="px-2.5 py-1 rounded-full text-[11px] font-semibold"
                :class="row.fridge.counted ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'"
              >
                {{ row.fridge.counted ? 'تم الجرد' : 'لم يُجرَد بعد' }}
              </span>
            </div>

            <template v-if="row.fridge.counted && row.fridge.summary">
              <div class="grid grid-cols-2 gap-2 text-xs sm:text-sm">
                <div class="rounded-lg bg-white border border-blue-100 p-2">
                  <div class="text-blue-800 font-semibold">زيادة إجمالية</div>
                  <div class="text-lg font-bold text-blue-900">+{{ formatQty(row.fridge.summary.surplus_total) }}</div>
                  <div class="mt-0.5 text-sm font-bold text-blue-800">{{ formatMoney(row.fridge.summary.surplus_value_total) }}</div>
                </div>
                <div class="rounded-lg bg-white border border-red-100 p-2">
                  <div class="text-red-700 font-semibold">عجز إجمالي</div>
                  <div class="text-lg font-bold text-red-800">-{{ formatQty(row.fridge.summary.shortage_total) }}</div>
                  <div class="mt-0.5 text-sm font-bold text-red-700">{{ formatMoney(row.fridge.summary.shortage_value_total) }}</div>
                </div>
              </div>
              <div class="text-sm text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                <span>مطابق: {{ row.fridge.summary.matched_count }} · بفروقات: {{ row.fridge.summary.variance_count }}</span>
                <span
                  v-if="row.fridge.summary.net_value !== undefined && row.fridge.summary.net_value !== null"
                  :class="Number(row.fridge.summary.net_value) >= 0 ? 'text-blue-800 font-semibold' : 'text-red-700 font-semibold'"
                >
                  صافي القيمة: {{ formatMoney(row.fridge.summary.net_value, true) }}
                </span>
              </div>

              <div v-if="(row.fridge.summary.shortage_items || []).length" class="space-y-2">
                <div class="font-bold text-red-800 text-sm">منتجات فيها عجز</div>
                <div
                  v-for="item in row.fridge.summary.shortage_items"
                  :key="`browse-shortage-${row.id}-${item.config_id}`"
                  class="rounded-lg border border-red-200 bg-white p-2.5"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-gray-900 text-sm">
                      {{ item.product_name }}
                      <span v-if="item.size" class="text-xs text-gray-500 font-normal">({{ translateSize(item.size) }})</span>
                    </div>
                    <div class="text-red-700 font-bold text-sm whitespace-nowrap">{{ formatMoney(Math.abs(item.diff_value || 0)) }}</div>
                  </div>
                  <div class="mt-1 text-xs text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                    <span>المسجّل: <strong>{{ formatQty(item.system_qty) }}</strong></span>
                    <span>الفعلي: <strong>{{ formatQty(item.actual_qty) }}</strong></span>
                    <span class="text-red-700 font-bold">العجز: {{ formatQty(Math.abs(item.diff_qty)) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="(row.fridge.summary.surplus_items || []).length" class="space-y-2">
                <div class="font-bold text-blue-800 text-sm">منتجات فيها زيادة</div>
                <div
                  v-for="item in row.fridge.summary.surplus_items"
                  :key="`browse-surplus-${row.id}-${item.config_id}`"
                  class="rounded-lg border border-blue-200 bg-white p-2.5"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-gray-900 text-sm">
                      {{ item.product_name }}
                      <span v-if="item.size" class="text-xs text-gray-500 font-normal">({{ translateSize(item.size) }})</span>
                    </div>
                    <div class="text-blue-800 font-bold text-sm whitespace-nowrap">+{{ formatMoney(Math.abs(item.diff_value || 0)) }}</div>
                  </div>
                  <div class="mt-1 text-xs text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                    <span>المسجّل: <strong>{{ formatQty(item.system_qty) }}</strong></span>
                    <span>الفعلي: <strong>{{ formatQty(item.actual_qty) }}</strong></span>
                    <span class="text-blue-800 font-bold">الزيادة: +{{ formatQty(item.diff_qty) }}</span>
                  </div>
                </div>
              </div>

              <div
                v-if="!(row.fridge.summary.shortage_items || []).length && !(row.fridge.summary.surplus_items || []).length"
                class="text-emerald-800 font-semibold text-sm"
              >
                كل المنتجات مطابقة للمخزون المسجّل.
              </div>
            </template>

            <div v-else class="text-sm text-amber-900">
              لم يتم إدخال جرد التلاجة لهذه التقفيلة بعد.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- عارض الصور مع اسم البند والتنقل -->
    <div
      v-if="lightboxOpen && currentLightboxPhoto"
      class="fixed inset-0 z-[80] bg-black/90 flex items-center justify-center p-3 sm:p-6"
      dir="rtl"
      @click.self="closeLightbox"
      @touchstart.passive="onLightboxTouchStart"
      @touchend.passive="onLightboxTouchEnd"
    >
      <button
        type="button"
        class="absolute top-4 left-4 z-10 text-white/90 hover:text-white bg-white/10 hover:bg-white/20 rounded-full w-10 h-10 text-2xl leading-none"
        aria-label="إغلاق"
        @click="closeLightbox"
      >
        ×
      </button>

      <button
        v-if="lightboxPhotos.length > 1"
        type="button"
        class="absolute right-2 sm:right-4 z-10 text-white bg-white/10 hover:bg-white/20 rounded-full w-11 h-11 text-2xl leading-none"
        aria-label="السابق"
        @click.stop="prevLightboxPhoto"
      >
        ‹
      </button>
      <button
        v-if="lightboxPhotos.length > 1"
        type="button"
        class="absolute left-2 sm:left-4 z-10 text-white bg-white/10 hover:bg-white/20 rounded-full w-11 h-11 text-2xl leading-none"
        aria-label="التالي"
        @click.stop="nextLightboxPhoto"
      >
        ›
      </button>

      <div class="relative w-full max-w-5xl max-h-[90vh] flex flex-col items-center gap-3">
        <div class="relative w-full flex items-center justify-center overflow-hidden rounded-xl bg-black/40">
          <img
            :src="currentLightboxPhoto.url"
            :alt="currentLightboxPhoto.title"
            class="max-h-[78vh] w-auto max-w-full object-contain select-none"
            draggable="false"
          />
          <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/45 to-transparent px-4 pt-10 pb-4">
            <div class="text-white text-lg sm:text-xl font-bold drop-shadow">
              {{ currentLightboxPhoto.title }}
            </div>
            <div v-if="currentLightboxPhoto.meta" class="text-white/80 text-xs sm:text-sm mt-1">
              {{ currentLightboxPhoto.meta }}
            </div>
          </div>
        </div>
        <div class="text-white/70 text-sm">
          {{ lightboxIndex + 1 }} / {{ lightboxPhotos.length }}
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';
import { translateSize } from '@/utils/productSizes';

export default {
  layout: AppLayout,
  props: {
    filters: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
    submissions: { type: Array, default: () => [] },
    typeLabels: { type: Object, required: true },
    canFilterAllBranches: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: true },
    enabled: { type: Boolean, default: false },
  },
  data() {
    return {
      localFilters: {
        business_date: this.filters.business_date,
        closing_type: this.filters.closing_type || '',
        branch_id: this.filters.branch_id || '',
      },
      lightboxOpen: false,
      lightboxPhotos: [],
      lightboxIndex: 0,
      touchStartX: null,
    };
  },
  computed: {
    currentLightboxPhoto() {
      return this.lightboxPhotos[this.lightboxIndex] || null;
    },
  },
  beforeUnmount() {
    this.unbindLightboxKeys();
  },
  methods: {
    translateSize,
    formatQty(value) {
      const n = Number(value);
      if (Number.isNaN(n)) return '—';
      return Number.isInteger(n) ? String(n) : n.toFixed(2);
    },
    formatMoney(value, signed = false) {
      const n = Number(value);
      if (Number.isNaN(n)) return '—';
      const abs = Math.abs(n).toFixed(2);
      const prefix = signed ? (n > 0 ? '+' : n < 0 ? '-' : '') : '';
      return `${prefix}${abs} ج.م`;
    },
    statusBadgeClass(row) {
      if (row.is_complete) return 'bg-green-100 text-green-800';
      if (row.uploaded_total > 0 || row.fridge?.counted) return 'bg-amber-100 text-amber-900';
      return 'bg-gray-100 text-gray-700';
    },
    applyFilters() {
      const params = {
        business_date: this.localFilters.business_date,
      };
      if (this.localFilters.closing_type) {
        params.closing_type = this.localFilters.closing_type;
      }
      if (this.canFilterAllBranches && this.localFilters.branch_id) {
        params.branch_id = this.localFilters.branch_id;
      }
      router.get(route('admin.closing-photo-reports.browse'), params, {
        preserveState: true,
        preserveScroll: true,
      });
    },
    openLightbox(row, itemId) {
      const photos = (row.items || [])
        .filter((item) => item.photo?.url)
        .map((item) => ({
          id: item.id,
          title: item.title,
          url: item.photo.url,
          meta: [
            row.branch_name,
            row.closing_type_label,
            item.photo.uploaded_at || null,
          ].filter(Boolean).join(' · '),
        }));

      if (!photos.length) {
        return;
      }

      const index = Math.max(0, photos.findIndex((photo) => photo.id === itemId));
      this.lightboxPhotos = photos;
      this.lightboxIndex = index === -1 ? 0 : index;
      this.lightboxOpen = true;
      this.bindLightboxKeys();
    },
    closeLightbox() {
      this.lightboxOpen = false;
      this.lightboxPhotos = [];
      this.lightboxIndex = 0;
      this.touchStartX = null;
      this.unbindLightboxKeys();
    },
    nextLightboxPhoto() {
      if (this.lightboxPhotos.length < 2) return;
      this.lightboxIndex = (this.lightboxIndex + 1) % this.lightboxPhotos.length;
    },
    prevLightboxPhoto() {
      if (this.lightboxPhotos.length < 2) return;
      this.lightboxIndex = (this.lightboxIndex - 1 + this.lightboxPhotos.length) % this.lightboxPhotos.length;
    },
    bindLightboxKeys() {
      this.unbindLightboxKeys();
      this._onLightboxKeydown = (event) => {
        if (!this.lightboxOpen) return;
        if (event.key === 'Escape') this.closeLightbox();
        if (event.key === 'ArrowLeft') this.nextLightboxPhoto();
        if (event.key === 'ArrowRight') this.prevLightboxPhoto();
      };
      window.addEventListener('keydown', this._onLightboxKeydown);
    },
    unbindLightboxKeys() {
      if (this._onLightboxKeydown) {
        window.removeEventListener('keydown', this._onLightboxKeydown);
        this._onLightboxKeydown = null;
      }
    },
    onLightboxTouchStart(event) {
      this.touchStartX = event.changedTouches?.[0]?.clientX ?? null;
    },
    onLightboxTouchEnd(event) {
      if (this.touchStartX == null) return;
      const endX = event.changedTouches?.[0]?.clientX ?? this.touchStartX;
      const delta = endX - this.touchStartX;
      this.touchStartX = null;
      if (Math.abs(delta) < 50) return;
      // في RTL: السحب لليسار = التالي، لليمين = السابق
      if (delta < 0) this.nextLightboxPhoto();
      else this.prevLightboxPhoto();
    },
  },
};
</script>
