<template>
  <AppLayout title="رفع صور التقفيلة">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">رفع صور التقفيلة</h2>
    </template>

    <div class="py-10" dir="rtl">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div v-if="flashSuccess" class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ flashSuccess }}</div>
        <div v-if="flashError || formError || cameraError" class="rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm">
          {{ flashError || formError || cameraError }}
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">{{ closingTypeLabel }}</h3>
              <p class="text-sm text-gray-600">
                الفرع: <strong>{{ branch.name }}</strong>
                · اليوم التشغيلي: <strong>{{ businessDate }}</strong>
              </p>
              <p class="text-sm text-gray-500 mt-1">
                صوّر كل بند إجباري بالكاميرا مباشرة حتى يُفتح لك تقرير المبيعات.
              </p>
            </div>
            <div class="flex flex-col items-stretch sm:items-end gap-2">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold self-start sm:self-end"
                :class="isComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
              >
                {{ isComplete ? 'مكتمل — يمكن فتح التقارير' : `متبقي ${remainingRequired} بند إجباري` }}
              </span>
              <a
                v-if="isComplete"
                :href="salesReportUrl"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm text-center"
              >
                فتح تقارير المبيعات
              </a>
            </div>
          </div>

          <div v-if="canChooseType" class="flex flex-wrap gap-2 text-sm">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border"
              :class="closingType === 'evening' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700'"
              @click="switchType('evening')"
            >
              التقفيلة الأولى
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border"
              :class="closingType === 'dawn' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700'"
              @click="switchType('dawn')"
            >
              التقفيلة الثانية
            </button>
          </div>
        </div>

        <div v-if="!items.length" class="bg-white shadow-xl sm:rounded-lg p-8 text-center text-gray-500">
          لا توجد بنود مفعّلة لهذا النوع من التقفيلة.
        </div>

        <div v-for="item in items" :key="item.id" class="bg-white shadow-xl sm:rounded-lg p-5 space-y-3">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h4 class="font-semibold text-gray-900">
                {{ item.title }}
                <span v-if="item.is_required" class="text-red-600 text-xs mr-1">* إجباري</span>
              </h4>
              <p v-if="item.description" class="text-sm text-gray-600 mt-1">{{ item.description }}</p>
            </div>
            <span
              class="text-xs px-2 py-1 rounded"
              :class="item.photo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
            >
              {{ item.photo ? 'تم التصوير' : 'بانتظار تصوير' }}
            </span>
          </div>

          <div v-if="item.photo" class="flex gap-3 items-start">
            <a :href="item.photo.url" target="_blank" rel="noopener" class="block w-28 h-28 rounded-lg overflow-hidden border">
              <img :src="item.photo.url" :alt="item.title" class="w-full h-full object-cover" />
            </a>
            <div class="text-xs text-gray-500 space-y-1">
              <div>{{ item.photo.original_name }}</div>
              <div>{{ item.photo.uploaded_at }}</div>
              <div>يمكنك إعادة التصوير واستبدال الصورة.</div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
              :disabled="uploadingId === item.id || cameraOpen"
              @click="openCamera(item)"
            >
              {{ item.photo ? 'إعادة التصوير بالكاميرا' : 'فتح الكاميرا والتصوير' }}
            </button>
            <input
              :ref="(el) => setCameraInputRef(item.id, el)"
              type="file"
              accept="image/*"
              capture="environment"
              class="hidden"
              :disabled="uploadingId === item.id"
              @change="(e) => onNativeCameraCapture(item, e)"
            />
            <span v-if="uploadingId === item.id" class="text-sm text-indigo-700">جاري الرفع...</span>
          </div>
        </div>
      </div>
    </div>

    <div
      v-if="cameraOpen"
      class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
      dir="rtl"
    >
      <div class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
        <div class="px-4 py-3 border-b flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="font-semibold text-gray-900 truncate">تصوير: {{ cameraItem?.title }}</div>
            <div class="text-xs text-gray-500">وجّه الكاميرا ثم اضغط التقاط</div>
          </div>
          <button type="button" class="text-sm text-gray-600 hover:text-gray-900" @click="closeCamera">إغلاق</button>
        </div>

        <div class="bg-black relative aspect-[3/4] sm:aspect-video">
          <video
            ref="videoEl"
            class="w-full h-full object-cover"
            autoplay
            playsinline
            muted
          />
          <canvas ref="canvasEl" class="hidden" />
        </div>

        <div class="p-4 flex flex-wrap gap-2 justify-center">
          <button
            type="button"
            class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-full text-sm font-semibold disabled:opacity-50"
            :disabled="!cameraReady || uploadingId"
            @click="captureFromStream"
          >
            التقاط صورة
          </button>
          <button
            type="button"
            class="border border-gray-300 text-gray-700 px-4 py-3 rounded-full text-sm"
            @click="useNativeCameraFallback"
          >
            استخدام كاميرا الجهاز
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { router, usePage } from '@inertiajs/vue3';

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
    return {
      uploadingId: null,
      cameraOpen: false,
      cameraReady: false,
      cameraItem: null,
      cameraStream: null,
      cameraError: null,
      cameraInputRefs: {},
    };
  },
  computed: {
    flashSuccess() {
      return usePage().props.flash?.success || null;
    },
    flashError() {
      return usePage().props.flash?.error || null;
    },
    formError() {
      const errors = usePage().props.errors || {};
      return errors.file || Object.values(errors)[0] || null;
    },
    isComplete() {
      if (this.submission?.is_complete) return true;
      const required = this.items.filter((i) => i.is_required);
      if (!required.length) return false;
      return required.every((i) => !!i.photo);
    },
    remainingRequired() {
      return this.items.filter((i) => i.is_required && !i.photo).length;
    },
  },
  beforeUnmount() {
    this.stopCameraStream();
  },
  methods: {
    setCameraInputRef(itemId, el) {
      if (el) {
        this.cameraInputRefs[itemId] = el;
      }
    },
    switchType(type) {
      router.get(route('admin.closing-photo-reports.submit'), {
        closing_type: type,
        business_date: this.businessDate,
      }, { preserveState: false });
    },
    async openCamera(item) {
      this.cameraError = null;
      this.cameraItem = item;
      this.cameraOpen = true;
      this.cameraReady = false;

      await this.$nextTick();

      if (!navigator.mediaDevices?.getUserMedia) {
        this.useNativeCameraFallback();
        return;
      }

      try {
        this.stopCameraStream();
        const stream = await navigator.mediaDevices.getUserMedia({
          audio: false,
          video: {
            facingMode: { ideal: 'environment' },
            width: { ideal: 1920 },
            height: { ideal: 1080 },
          },
        });
        this.cameraStream = stream;
        const video = this.$refs.videoEl;
        if (video) {
          video.srcObject = stream;
          await video.play().catch(() => {});
        }
        this.cameraReady = true;
      } catch (e) {
        console.error(e);
        this.cameraError = 'تعذر فتح الكاميرا من المتصفح. سيتم فتح كاميرا الجهاز.';
        this.useNativeCameraFallback();
      }
    },
    useNativeCameraFallback() {
      const item = this.cameraItem;
      this.closeCamera();
      if (!item) return;
      const input = this.cameraInputRefs[item.id];
      if (input) {
        input.click();
      }
    },
    closeCamera() {
      this.stopCameraStream();
      this.cameraOpen = false;
      this.cameraReady = false;
      this.cameraItem = null;
    },
    stopCameraStream() {
      if (this.cameraStream) {
        this.cameraStream.getTracks().forEach((track) => track.stop());
        this.cameraStream = null;
      }
      const video = this.$refs.videoEl;
      if (video) {
        video.srcObject = null;
      }
    },
    async captureFromStream() {
      const item = this.cameraItem;
      const video = this.$refs.videoEl;
      const canvas = this.$refs.canvasEl;
      if (!item || !video || !canvas || !this.cameraReady) return;

      const width = video.videoWidth || 1280;
      const height = video.videoHeight || 720;
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, width, height);

      const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
      if (!blob) {
        this.cameraError = 'تعذر التقاط الصورة.';
        return;
      }

      const fileName = `closing_${item.id}_${Date.now()}.jpg`;
      const file = new File([blob], fileName, { type: 'image/jpeg' });
      this.closeCamera();
      this.uploadFile(item, file);
    },
    onNativeCameraCapture(item, event) {
      const file = event.target.files?.[0];
      event.target.value = '';
      if (!file) return;
      this.uploadFile(item, file);
    },
    uploadFile(item, file) {
      this.uploadingId = item.id;
      this.cameraError = null;
      router.post(
        route('admin.closing-photo-reports.upload'),
        {
          item_id: item.id,
          closing_type: this.closingType,
          business_date: this.businessDate,
          file,
        },
        {
          forceFormData: true,
          preserveScroll: true,
          onFinish: () => {
            this.uploadingId = null;
          },
        }
      );
    },
  },
};
</script>
