<template>
  <AppLayout title="رفع صور التقفيلة">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">رفع صور التقفيلة</h2>
    </template>

    <div class="py-8" dir="rtl">
      <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div v-if="statusMessage" class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">
          {{ statusMessage }}
        </div>
        <div v-if="errorMessage" class="rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm">
          {{ errorMessage }}
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-5 space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ closingTypeLabel }}</h3>
              <p class="text-sm text-gray-600">
                {{ branch.name }} · {{ businessDate }}
              </p>
            </div>
            <span
              class="px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
              :class="localComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
            >
              {{ statusBadge }}
            </span>
          </div>

          <div class="flex gap-2 text-xs font-semibold">
            <span
              class="px-2 py-1 rounded-full"
              :class="photosComplete ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
            >
              1) الصور {{ photosComplete ? '✓' : `${doneRequired}/${requiredItems.length}` }}
            </span>
            <span
              v-if="fridgeRequired"
              class="px-2 py-1 rounded-full"
              :class="fridgeCounted ? 'bg-emerald-100 text-emerald-800' : 'bg-cyan-100 text-cyan-900'"
            >
              2) أعداد التلاجة {{ fridgeCounted ? '✓' : 'مطلوب' }}
            </span>
          </div>

          <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
            <div
              class="h-full bg-emerald-500 transition-all duration-300"
              :style="{ width: progressPercent + '%' }"
            />
          </div>

          <a
            v-if="localComplete"
            :href="salesReportUrl"
            class="block text-center bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-xl text-sm font-semibold"
          >
            فتح تقارير المبيعات
          </a>
        </div>

        <div v-if="canChooseType" class="flex gap-2 text-sm">
          <button
            type="button"
            class="flex-1 px-3 py-2 rounded-lg border"
            :class="closingType === 'evening' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white'"
            @click="switchType('evening')"
          >
            التقفيلة الأولى
          </button>
          <button
            type="button"
            class="flex-1 px-3 py-2 rounded-lg border"
            :class="closingType === 'dawn' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white'"
            @click="switchType('dawn')"
          >
            التقفيلة الثانية
          </button>
        </div>

        <!-- مرحلة الصور -->
        <template v-if="phase === 'photos'">
          <div v-if="!localItems.length" class="bg-white shadow-xl sm:rounded-lg p-8 text-center text-gray-500">
            لا توجد بنود مفعّلة لهذا النوع من التقفيلة.
          </div>

          <template v-else>
            <div
              v-if="activeItem"
              class="bg-white shadow-xl sm:rounded-lg p-5 space-y-4 ring-2 ring-emerald-400"
            >
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="text-xs text-gray-500 mb-1">
                    البند {{ activeIndex + 1 }} من {{ localItems.length }}
                  </div>
                  <h4 class="font-semibold text-gray-900 text-lg">
                    {{ activeItem.title }}
                    <span v-if="activeItem.is_required" class="text-red-600 text-xs">* إجباري</span>
                  </h4>
                  <p v-if="activeItem.description" class="text-sm text-gray-600 mt-1">{{ activeItem.description }}</p>
                </div>
                <span
                  class="text-xs px-2 py-1 rounded font-semibold"
                  :class="activeItem.photo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                >
                  {{ activeItem.photo ? 'تم' : 'مطلوب' }}
                </span>
              </div>

              <div v-if="activeItem.photo" class="rounded-xl overflow-hidden border bg-gray-50">
                <img
                  :src="activeItem.photo.preview_url || activeItem.photo.url"
                  :alt="activeItem.title"
                  class="w-full max-h-64 object-contain bg-black/5"
                  loading="lazy"
                />
              </div>

              <input
                ref="cameraInput"
                type="file"
                accept="image/*"
                capture="environment"
                class="hidden"
                :disabled="uploading"
                @change="onCameraSelected"
              />

              <button
                type="button"
                class="w-full bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white px-4 py-4 rounded-xl text-base font-bold disabled:opacity-50"
                :disabled="uploading"
                @click="triggerCamera"
              >
                <span v-if="uploading">جاري الرفع...</span>
                <span v-else-if="activeItem.photo">إعادة التصوير</span>
                <span v-else>تصوير الآن</span>
              </button>

              <div class="flex gap-2">
                <button
                  type="button"
                  class="flex-1 border border-gray-300 text-gray-700 px-3 py-3 rounded-xl text-sm disabled:opacity-40"
                  :disabled="activeIndex <= 0 || uploading"
                  @click="goPrev"
                >
                  السابق
                </button>
                <button
                  type="button"
                  class="flex-1 bg-slate-800 text-white px-3 py-3 rounded-xl text-sm disabled:opacity-40"
                  :disabled="activeIndex >= localItems.length - 1 || uploading"
                  @click="goNext"
                >
                  التالي
                </button>
              </div>
            </div>

            <div class="bg-white shadow-xl sm:rounded-lg p-4 space-y-2">
              <div class="text-sm font-semibold text-gray-800 mb-2">كل البنود</div>
              <button
                v-for="(item, idx) in localItems"
                :key="item.id"
                type="button"
                class="w-full flex items-center justify-between gap-2 text-right px-3 py-2.5 rounded-lg border text-sm"
                :class="idx === activeIndex
                  ? 'border-emerald-400 bg-emerald-50'
                  : (item.photo ? 'border-green-200 bg-green-50/50' : 'border-gray-200')"
                :disabled="uploading"
                @click="activeIndex = idx"
              >
                <span class="truncate">
                  <span class="text-gray-400 ml-1">{{ idx + 1 }}.</span>
                  {{ item.title }}
                </span>
                <span
                  class="shrink-0 text-[11px] px-2 py-0.5 rounded-full font-semibold"
                  :class="item.photo ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
                >
                  {{ item.photo ? 'تم' : 'متبقي' }}
                </span>
              </button>
            </div>

            <button
              v-if="photosComplete && fridgeRequired && !fridgeCounted"
              type="button"
              class="w-full bg-cyan-700 hover:bg-cyan-800 text-white px-4 py-4 rounded-xl text-base font-bold"
              @click="phase = 'fridge'"
            >
              التالي: إدخال أعداد التلاجة الفعلية
            </button>
          </template>
        </template>

        <!-- مرحلة أعداد التلاجة -->
        <template v-else-if="phase === 'fridge'">
          <div class="bg-white shadow-xl sm:rounded-lg p-5 space-y-4">
            <div>
              <h4 class="text-lg font-bold text-gray-900">أعداد التلاجة الفعلية</h4>
              <p class="text-sm text-gray-600 mt-1">
                <template v-if="!fridgeCounted">
                  أدخل العدد الموجود فعلياً لكل منتج نشط في تلاجة فرع
                  <strong>{{ branch.name }}</strong>.
                  بعد الحفظ يقارن النظام بالمسجّل ويسوّي المخزون حسب الواقع (مرة واحدة فقط).
                </template>
                <template v-else>
                  تم حفظ جرد تلاجة فرع <strong>{{ branch.name }}</strong> وتسوية المخزون.
                  النتيجة أدناه نهائية ولا يمكن إعادة الحفظ.
                </template>
              </p>
            </div>

            <div v-if="fridgeSummary" class="rounded-xl border border-cyan-200 bg-cyan-50 p-3 text-sm space-y-3">
              <div class="font-semibold text-cyan-900">{{ fridgeCounted ? 'نتيجة التسوية' : 'نتيجة آخر تسوية' }}</div>
              <div class="grid grid-cols-2 gap-2 text-xs sm:text-sm">
                <div class="rounded-lg bg-white border border-blue-100 p-2">
                  <div class="text-blue-800 font-semibold">زيادة إجمالية</div>
                  <div class="text-lg font-bold text-blue-900">+{{ formatQty(fridgeSummary.surplus_total) }}</div>
                  <div class="mt-0.5 text-sm font-bold text-blue-800">{{ formatMoney(fridgeSummary.surplus_value_total) }}</div>
                </div>
                <div class="rounded-lg bg-white border border-red-100 p-2">
                  <div class="text-red-700 font-semibold">عجز إجمالي</div>
                  <div class="text-lg font-bold text-red-800">-{{ formatQty(fridgeSummary.shortage_total) }}</div>
                  <div class="mt-0.5 text-sm font-bold text-red-700">{{ formatMoney(fridgeSummary.shortage_value_total) }}</div>
                </div>
              </div>
              <div class="text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                <span>مطابق: {{ fridgeSummary.matched_count }} · بفروقات: {{ fridgeSummary.variance_count }}</span>
                <span
                  v-if="fridgeSummary.net_value !== undefined && fridgeSummary.net_value !== null"
                  :class="Number(fridgeSummary.net_value) >= 0 ? 'text-blue-800 font-semibold' : 'text-red-700 font-semibold'"
                >
                  صافي القيمة: {{ formatMoney(fridgeSummary.net_value, true) }}
                </span>
              </div>

              <div v-if="(fridgeSummary.shortage_items || []).length" class="space-y-2">
                <div class="font-bold text-red-800">منتجات فيها عجز</div>
                <div
                  v-for="item in fridgeSummary.shortage_items"
                  :key="'shortage-' + item.config_id"
                  class="rounded-lg border border-red-200 bg-white p-2.5"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-gray-900">
                      {{ item.product_name }}
                      <span v-if="item.size" class="text-xs text-gray-500 font-normal">({{ translateSize(item.size) }})</span>
                    </div>
                    <div class="text-red-700 font-bold whitespace-nowrap">{{ formatMoney(Math.abs(item.diff_value || 0)) }}</div>
                  </div>
                  <div class="mt-1 text-xs text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                    <span>المسجّل: <strong>{{ formatQty(item.system_qty) }}</strong></span>
                    <span>الفعلي: <strong>{{ formatQty(item.actual_qty) }}</strong></span>
                    <span class="text-red-700 font-bold">العجز: {{ formatQty(Math.abs(item.diff_qty)) }}</span>
                    <span v-if="item.unit_price">سعر الوحدة: {{ formatMoney(item.unit_price) }}</span>
                  </div>
                </div>
              </div>

              <div v-if="(fridgeSummary.surplus_items || []).length" class="space-y-2">
                <div class="font-bold text-blue-800">منتجات فيها زيادة</div>
                <div
                  v-for="item in fridgeSummary.surplus_items"
                  :key="'surplus-' + item.config_id"
                  class="rounded-lg border border-blue-200 bg-white p-2.5"
                >
                  <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-gray-900">
                      {{ item.product_name }}
                      <span v-if="item.size" class="text-xs text-gray-500 font-normal">({{ translateSize(item.size) }})</span>
                    </div>
                    <div class="text-blue-800 font-bold whitespace-nowrap">+{{ formatMoney(Math.abs(item.diff_value || 0)) }}</div>
                  </div>
                  <div class="mt-1 text-xs text-gray-700 flex flex-wrap gap-x-3 gap-y-1">
                    <span>المسجّل: <strong>{{ formatQty(item.system_qty) }}</strong></span>
                    <span>الفعلي: <strong>{{ formatQty(item.actual_qty) }}</strong></span>
                    <span class="text-blue-800 font-bold">الزيادة: +{{ formatQty(item.diff_qty) }}</span>
                    <span v-if="item.unit_price">سعر الوحدة: {{ formatMoney(item.unit_price) }}</span>
                  </div>
                </div>
              </div>

              <div
                v-if="!(fridgeSummary.shortage_items || []).length && !(fridgeSummary.surplus_items || []).length"
                class="text-emerald-800 font-semibold"
              >
                كل المنتجات مطابقة للمخزون المسجّل.
              </div>
            </div>

            <div v-if="!fridgeCounted" class="space-y-3">
              <div
                v-for="product in fridgeInputs"
                :key="product.config_id"
                class="border border-gray-200 rounded-xl p-3 space-y-2"
              >
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <div class="font-semibold text-gray-900">{{ product.product_name }}</div>
                    <div v-if="product.size" class="text-xs text-gray-500">المقاس: {{ translateSize(product.size) }}</div>
                  </div>
                  <div class="text-xs text-gray-600 text-left">
                    المسجّل:
                    <span class="font-bold text-gray-900">{{ formatQty(product.registered_qty) }}</span>
                  </div>
                </div>

                <label class="block text-xs font-semibold text-gray-700">العدد الفعلي *</label>
                <input
                  v-model.number="product.actual_qty"
                  type="number"
                  step="any"
                  class="w-full border rounded-lg px-3 py-2.5 text-base"
                  :disabled="savingFridge"
                  required
                />

                <div class="text-xs font-semibold" :class="diffClass(liveDiff(product))">
                  {{ diffLabel(liveDiff(product)) }}
                </div>
              </div>
            </div>

            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 border border-gray-300 text-gray-700 px-3 py-3 rounded-xl text-sm"
                :disabled="savingFridge"
                @click="phase = 'photos'"
              >
                رجوع للصور
              </button>
              <button
                v-if="!fridgeCounted"
                type="button"
                class="flex-[2] bg-cyan-700 hover:bg-cyan-800 text-white px-3 py-3 rounded-xl text-sm font-bold disabled:opacity-50"
                :disabled="savingFridge || !fridgeInputsReady"
                @click="submitFridgeCounts"
              >
                {{ savingFridge ? 'جاري الحفظ والتسوية...' : 'حفظ ومقارنة وتسوية' }}
              </button>
              <a
                v-else
                :href="salesReportUrl"
                class="flex-[2] text-center bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-3 rounded-xl text-sm font-bold"
              >
                فتح تقارير المبيعات
              </a>
            </div>
          </div>
        </template>
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
    branch: { type: Object, required: true },
    closingType: { type: String, required: true },
    closingTypeLabel: { type: String, required: true },
    businessDate: { type: String, required: true },
    enabled: { type: Boolean, default: false },
    currentRequirement: { type: Object, default: null },
    submission: { type: Object, default: null },
    items: { type: Array, default: () => [] },
    fridge: { type: Object, default: () => ({ required: false, counted: false, products: [], summary: null }) },
    canChooseType: { type: Boolean, default: false },
    salesReportUrl: { type: String, required: true },
  },
  data() {
    const localItems = (this.items || []).map((item) => ({ ...item, photo: item.photo ? { ...item.photo } : null }));
    const firstPending = localItems.findIndex((item) => item.is_required && !item.photo);
    const fridge = this.fridge || { required: false, counted: false, products: [], summary: null };
    const photosDone = this.computePhotosComplete(localItems, this.submission);

    return {
      localItems,
      localSubmission: this.submission ? { ...this.submission } : null,
      localFridge: { ...fridge, products: [...(fridge.products || [])] },
      fridgeInputs: [],
      phase: photosDone && fridge.required && !fridge.counted ? 'fridge' : 'photos',
      activeIndex: firstPending >= 0 ? firstPending : 0,
      uploading: false,
      savingFridge: false,
      statusMessage: null,
      errorMessage: null,
    };
  },
  computed: {
    activeItem() {
      return this.localItems[this.activeIndex] || null;
    },
    requiredItems() {
      return this.localItems.filter((i) => i.is_required);
    },
    doneRequired() {
      return this.requiredItems.filter((i) => !!i.photo).length;
    },
    photosComplete() {
      return this.computePhotosComplete(this.localItems, this.localSubmission);
    },
    fridgeRequired() {
      return !!(this.localFridge?.required && (this.localFridge.products || []).length);
    },
    fridgeCounted() {
      return !!(this.localFridge?.counted || this.localSubmission?.fridge_counted_at);
    },
    fridgeSummary() {
      return this.localFridge?.summary || null;
    },
    localComplete() {
      if (this.localSubmission?.is_complete) return true;
      if (!this.photosComplete) return false;
      if (this.fridgeRequired && !this.fridgeCounted) return false;
      return true;
    },
    fridgeInputsReady() {
      return this.fridgeInputs.length > 0
        && this.fridgeInputs.every((p) => p.actual_qty !== null && p.actual_qty !== '' && !Number.isNaN(Number(p.actual_qty)));
    },
    statusBadge() {
      if (this.localComplete) return 'مكتمل';
      if (this.photosComplete && this.fridgeRequired && !this.fridgeCounted) return 'متبقي: التلاجة';
      return `${this.doneRequired}/${this.requiredItems.length}`;
    },
    progressPercent() {
      const photoPart = this.requiredItems.length
        ? this.doneRequired / this.requiredItems.length
        : 0;
      if (!this.fridgeRequired) {
        return Math.round(photoPart * 100);
      }
      const fridgePart = this.fridgeCounted ? 1 : 0;
      return Math.round(((photoPart + fridgePart) / 2) * 100);
    },
  },
  watch: {
    items: {
      deep: true,
      handler(value) {
        this.localItems = (value || []).map((item) => ({ ...item, photo: item.photo ? { ...item.photo } : null }));
      },
    },
    fridge: {
      deep: true,
      immediate: true,
      handler(value) {
        this.localFridge = { ...(value || {}), products: [...((value && value.products) || [])] };
        this.syncFridgeInputs();
      },
    },
  },
  methods: {
    translateSize,
    computePhotosComplete(items, submission) {
      if (submission?.photos_complete) return true;
      const required = (items || []).filter((i) => i.is_required);
      if (!required.length) return false;
      return required.every((i) => !!i.photo);
    },
    syncFridgeInputs() {
      const next = this.buildFridgeInputs(this.localFridge);
      const prevById = Object.fromEntries(
        (this.fridgeInputs || []).map((p) => [p.config_id, p.actual_qty])
      );
      this.fridgeInputs = next.map((p) => ({
        ...p,
        actual_qty: prevById[p.config_id] !== undefined && prevById[p.config_id] !== null
          ? prevById[p.config_id]
          : p.actual_qty,
      }));
    },
    buildFridgeInputs(fridge) {
      return (fridge?.products || [])
        .filter((p) => !p.exclude_from_closing_count)
        .map((p) => ({
          config_id: p.config_id,
          product_id: p.product_id,
          product_name: p.product_name,
          size: p.size || '',
          registered_qty: Number(p.registered_qty ?? 0),
          actual_qty: p.actual_qty === null || p.actual_qty === undefined ? null : Number(p.actual_qty),
        }));
    },
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
    liveDiff(product) {
      if (product.actual_qty === null || product.actual_qty === '' || Number.isNaN(Number(product.actual_qty))) {
        return null;
      }
      return Number(product.actual_qty) - Number(product.registered_qty || 0);
    },
    diffLabel(diff) {
      if (diff === null) return 'أدخل العدد للمقارنة';
      if (Math.abs(diff) <= 0.0001) return 'مطابق للمخزون المسجّل';
      if (diff > 0) return `زيادة: +${this.formatQty(diff)}`;
      return `عجز: ${this.formatQty(diff)}`;
    },
    diffClass(diff) {
      if (diff === null) return 'text-gray-500';
      if (Math.abs(diff) <= 0.0001) return 'text-emerald-700';
      if (diff > 0) return 'text-blue-700';
      return 'text-red-700';
    },
    switchType(type) {
      router.get(route('admin.closing-photo-reports.submit'), {
        closing_type: type,
        business_date: this.businessDate,
      }, { preserveState: false });
    },
    goPrev() {
      if (this.activeIndex > 0) this.activeIndex -= 1;
    },
    goNext() {
      if (this.activeIndex < this.localItems.length - 1) this.activeIndex += 1;
    },
    jumpToNextPending(fromIndex = this.activeIndex) {
      const next = this.localItems.findIndex((item, idx) => idx > fromIndex && item.is_required && !item.photo);
      if (next >= 0) {
        this.activeIndex = next;
        return;
      }
      const any = this.localItems.findIndex((item) => item.is_required && !item.photo);
      if (any >= 0) {
        this.activeIndex = any;
        return;
      }
      if (this.photosComplete && this.fridgeRequired && !this.fridgeCounted) {
        this.phase = 'fridge';
      }
    },
    triggerCamera() {
      this.errorMessage = null;
      this.statusMessage = null;
      const input = this.$refs.cameraInput;
      if (input) {
        input.value = '';
        input.click();
      }
    },
    async onCameraSelected(event) {
      const file = event.target.files?.[0];
      event.target.value = '';
      if (!file || !this.activeItem) return;

      this.uploading = true;
      this.errorMessage = null;
      this.statusMessage = null;

      const itemId = this.activeItem.id;
      const itemIndex = this.activeIndex;
      let previewUrl = null;

      try {
        const compressed = await this.compressImage(file);
        previewUrl = URL.createObjectURL(compressed);

        const item = this.localItems.find((i) => i.id === itemId);
        if (item) {
          item.photo = {
            ...(item.photo || {}),
            preview_url: previewUrl,
            url: previewUrl,
            original_name: compressed.name,
            uploaded_at: 'جاري الرفع...',
          };
        }

        const formData = new FormData();
        formData.append('item_id', String(itemId));
        formData.append('closing_type', this.closingType);
        formData.append('business_date', this.businessDate);
        formData.append('file', compressed, compressed.name || `closing_${itemId}.jpg`);

        const response = await fetch(route('admin.closing-photo-reports.upload'), {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: formData,
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
          throw new Error(data.message || data.errors?.file?.[0] || 'فشل رفع الصورة');
        }

        if (Array.isArray(data.items)) {
          this.localItems = data.items.map((row) => ({ ...row, photo: row.photo ? { ...row.photo } : null }));
        } else if (data.photo) {
          const target = this.localItems.find((i) => i.id === itemId);
          if (target) target.photo = { ...data.photo };
        }

        if (data.submission) {
          this.localSubmission = data.submission;
        }
        if (data.fridge) {
          this.localFridge = { ...data.fridge, products: [...(data.fridge.products || [])] };
          this.fridgeInputs = this.buildFridgeInputs(this.localFridge);
        }

        this.statusMessage = data.message || 'تم الرفع';
        this.jumpToNextPending(itemIndex);
      } catch (e) {
        console.error(e);
        this.errorMessage = e.message || 'حدث خطأ أثناء الرفع';
        const item = this.localItems.find((i) => i.id === itemId);
        if (item && item.photo?.uploaded_at === 'جاري الرفع...') {
          item.photo = null;
        }
      } finally {
        this.uploading = false;
        if (previewUrl) {
          setTimeout(() => URL.revokeObjectURL(previewUrl), 15000);
        }
      }
    },
    async submitFridgeCounts() {
      if (!this.fridgeInputsReady) {
        this.errorMessage = 'أدخل العدد الفعلي لكل المنتجات.';
        return;
      }

      this.savingFridge = true;
      this.errorMessage = null;
      this.statusMessage = null;

      try {
        const response = await fetch(route('admin.closing-photo-reports.fridge-count'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            closing_type: this.closingType,
            business_date: this.businessDate,
            counts: this.fridgeInputs.map((p) => ({
              config_id: p.config_id,
              actual_qty: Number(p.actual_qty),
            })),
          }),
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          throw new Error(data.message || data.errors?.fridge?.[0] || data.errors?.counts?.[0] || 'فشل حفظ أعداد التلاجة');
        }

        if (data.submission) {
          this.localSubmission = data.submission;
        }
        if (data.fridge) {
          this.localFridge = { ...data.fridge, products: [...(data.fridge.products || [])] };
          this.fridgeInputs = this.buildFridgeInputs(this.localFridge);
        }

        this.statusMessage = data.message || 'تم حفظ أعداد التلاجة وتسوية المخزون.';
      } catch (e) {
        console.error(e);
        this.errorMessage = e.message || 'حدث خطأ أثناء حفظ أعداد التلاجة';
      } finally {
        this.savingFridge = false;
      }
    },
    async compressImage(file, maxWidth = 1280, quality = 0.72) {
      if (file.size && file.size < 350 * 1024 && file.type === 'image/jpeg') {
        return file;
      }

      const bitmap = await createImageBitmap(file).catch(() => null);
      if (!bitmap) {
        return file;
      }

      try {
        const ratio = Math.min(1, maxWidth / Math.max(bitmap.width, bitmap.height));
        const width = Math.max(1, Math.round(bitmap.width * ratio));
        const height = Math.max(1, Math.round(bitmap.height * ratio));

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d', { alpha: false });
        ctx.drawImage(bitmap, 0, 0, width, height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        bitmap.close?.();

        if (!blob) return file;

        return new File([blob], `closing_${Date.now()}.jpg`, {
          type: 'image/jpeg',
          lastModified: Date.now(),
        });
      } catch (e) {
        bitmap.close?.();
        return file;
      }
    },
  },
};
</script>
