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
              {{ localComplete ? 'مكتمل' : `${doneRequired}/${requiredItems.length}` }}
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

        <div v-if="!localItems.length" class="bg-white shadow-xl sm:rounded-lg p-8 text-center text-gray-500">
          لا توجد بنود مفعّلة لهذا النوع من التقفيلة.
        </div>

        <template v-else>
          <!-- البند الحالي فقط لتسهيل الجوال -->
          <div
            v-if="activeItem"
            :ref="'item-' + activeItem.id"
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
              <span v-if="uploading">جاري الضغط والرفع...</span>
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

          <!-- قائمة مختصرة لكل البنود -->
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
        </template>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router } from '@inertiajs/vue3';

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
    canChooseType: { type: Boolean, default: false },
    salesReportUrl: { type: String, required: true },
  },
  data() {
    const localItems = (this.items || []).map((item) => ({ ...item, photo: item.photo ? { ...item.photo } : null }));
    const firstPending = localItems.findIndex((item) => item.is_required && !item.photo);

    return {
      localItems,
      localSubmission: this.submission ? { ...this.submission } : null,
      activeIndex: firstPending >= 0 ? firstPending : 0,
      uploading: false,
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
    localComplete() {
      if (this.localSubmission?.is_complete) return true;
      if (!this.requiredItems.length) return false;
      return this.requiredItems.every((i) => !!i.photo);
    },
    progressPercent() {
      if (!this.requiredItems.length) return 0;
      return Math.round((this.doneRequired / this.requiredItems.length) * 100);
    },
  },
  watch: {
    items: {
      deep: true,
      handler(value) {
        this.localItems = (value || []).map((item) => ({ ...item, photo: item.photo ? { ...item.photo } : null }));
      },
    },
  },
  methods: {
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
      if (any >= 0) this.activeIndex = any;
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

        // معاينة فورية قبل انتهاء الرفع
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

        this.statusMessage = data.message || 'تم الرفع';
        this.jumpToNextPending(itemIndex);
      } catch (e) {
        console.error(e);
        this.errorMessage = e.message || 'حدث خطأ أثناء الرفع';
        // أعد الحالة لو فشل
        const item = this.localItems.find((i) => i.id === itemId);
        if (item && item.photo?.uploaded_at === 'جاري الرفع...') {
          item.photo = null;
        }
      } finally {
        this.uploading = false;
        if (previewUrl) {
          // لا نلغي الـ blob فوراً لو لسه ظاهر؛ نلغي بعد استبداله برابط السيرفر
          setTimeout(() => URL.revokeObjectURL(previewUrl), 15000);
        }
      }
    },
    async compressImage(file, maxWidth = 1280, quality = 0.72) {
      // ملفات صغيرة جداً نرفعها كما هي
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
