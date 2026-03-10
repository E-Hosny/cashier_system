<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">عرض الشاشة</h1>
    </div>
    <p class="text-gray-600 mb-6">إدارة صور العرض على الشاشة الكبيرة (المنتجات، المنيو، العروض). حدد مدة عرض كل صورة بالثواني.</p>

    <!-- رفع صورة -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">رفع صورة جديدة</h2>
      <form @submit.prevent="uploadImage" class="flex flex-wrap items-end gap-4">
        <input
          ref="fileInput"
          type="file"
          accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
          class="p-2 border border-gray-300 rounded-lg"
          @change="onFileSelect"
        />
        <div class="flex flex-col gap-1">
          <label class="text-gray-700 font-medium">مدة العرض (ثانية) 1–60</label>
          <input
            v-model.number="newSlideDuration"
            type="number"
            min="1"
            max="60"
            class="p-3 border border-gray-300 rounded-lg w-24"
          />
        </div>
        <button type="submit" class="btn-green" :disabled="!selectedFile">رفع</button>
      </form>
    </div>

    <!-- قائمة الصور -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">الصور الحالية (الترتيب من أعلى لأسفل)</h2>
      <div v-if="slides.length === 0" class="text-gray-500 py-8 text-center">لا توجد صور. ارفع صوراً لعرضها على الشاشة.</div>
      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div
          v-for="(slide, index) in slides"
          :key="slide.id"
          class="border border-gray-200 rounded-lg overflow-hidden bg-gray-50"
        >
          <img :src="slide.url" :alt="'شريحة ' + (index + 1)" class="w-full h-40 object-cover" />
          <div class="p-2 space-y-2">
            <div class="flex justify-between items-center gap-2">
              <span class="text-sm text-gray-600">#{{ index + 1 }}</span>
              <div class="flex items-center gap-1">
                <label class="text-xs text-gray-500">ثانية:</label>
                <input
                  :value="slide.duration_seconds"
                  type="number"
                  min="1"
                  max="60"
                  class="w-12 p-1 border border-gray-300 rounded text-sm text-center"
                  @change="updateDuration(slide.id, $event.target.value)"
                />
              </div>
            </div>
            <button
              type="button"
              @click="deleteSlide(slide.id)"
              class="btn-red text-sm py-1 px-2 w-full"
            >
              حذف
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- رابط العرض العام (كل فرع له رابط خاص، مفتوح للجميع بدون تسجيل دخول) -->
    <div class="bg-white rounded-xl shadow-lg p-6">
      <p class="text-gray-600 mb-2">رابط عرض الشاشة لهذا الفرع (يمكن فتحه على أي جهاز بدون تسجيل):</p>
      <div class="flex flex-wrap items-center gap-2">
        <a
          :href="displayUrl"
          target="_blank"
          rel="noopener noreferrer"
          class="btn-primary inline-block"
        >
          فتح عرض الشاشة في تاب جديد
        </a>
        <input
          type="text"
          :value="displayUrl"
          readonly
          class="flex-1 min-w-[200px] p-2 border border-gray-300 rounded-lg bg-gray-50 text-sm font-mono"
          @click="$event.target.select()"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { Inertia } from '@inertiajs/inertia';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
  layout: AppLayout,
  props: {
    slides: Array,
    displayPublicUrl: { type: String, default: '' },
  },
  data() {
    return {
      newSlideDuration: 3,
      selectedFile: null,
    };
  },
  computed: {
    displayUrl() {
      const fromProp = this.displayPublicUrl;
      const fromPage = this.$page?.props?.displayPublicUrl;
      return fromProp || fromPage || (window.location.origin + '/display');
    },
  },
  methods: {
    onFileSelect(event) {
      this.selectedFile = event.target.files?.[0] || null;
    },
    uploadImage() {
      if (!this.selectedFile) return;
      const duration = Math.max(1, Math.min(60, Number(this.newSlideDuration) || 3));
      const formData = new FormData();
      formData.append('image', this.selectedFile);
      formData.append('duration_seconds', duration);
      Inertia.post(route('admin.display-screen.slides.store'), formData, {
        forceFormData: true,
        onSuccess: () => {
          this.selectedFile = null;
          this.newSlideDuration = 3;
          if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
      });
    },
    updateDuration(slideId, value) {
      const sec = Math.max(1, Math.min(60, parseInt(value, 10) || 3));
      Inertia.put(route('admin.display-screen.slides.update', slideId), { duration_seconds: sec });
    },
    deleteSlide(id) {
      if (!confirm('حذف هذه الصورة؟')) return;
      Inertia.delete(route('admin.display-screen.slides.destroy', id));
    },
  },
};
</script>

<style scoped>
.btn-primary {
  @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-green {
  @apply bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition;
}
.btn-red {
  @apply bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded transition text-sm;
}
</style>
