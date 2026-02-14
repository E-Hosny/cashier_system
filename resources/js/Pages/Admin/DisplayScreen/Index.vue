<template>
  <div class="container mx-auto p-4 sm:p-6" dir="rtl">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
      <h1 class="text-3xl font-bold text-gray-800">عرض الشاشة</h1>
    </div>
    <p class="text-gray-600 mb-6">إدارة صور العرض على الشاشة الكبيرة (المنتجات، المنيو، العروض).</p>

    <!-- مدة الشريحة -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">مدة كل شريحة (ثانية)</h2>
      <form @submit.prevent="saveConfig" class="flex flex-wrap items-end gap-4">
        <div class="flex flex-col gap-1">
          <label class="text-gray-700 font-medium">من 1 إلى 60 ثانية</label>
          <input
            v-model.number="intervalSeconds"
            type="number"
            min="1"
            max="60"
            class="p-3 border border-gray-300 rounded-lg w-32"
          />
        </div>
        <button type="submit" class="btn-primary">حفظ</button>
      </form>
    </div>

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
          <div class="p-2 flex justify-between items-center">
            <span class="text-sm text-gray-600">#{{ index + 1 }}</span>
            <button
              type="button"
              @click="deleteSlide(slide.id)"
              class="btn-red text-sm py-1 px-2"
            >
              حذف
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- رابط العرض العام -->
    <div class="bg-white rounded-xl shadow-lg p-6">
      <a
        :href="displayUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="btn-primary inline-block"
      >
        فتح عرض الشاشة في تاب جديد
      </a>
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
    interval_seconds: Number,
  },
  data() {
    return {
      intervalSeconds: this.interval_seconds ?? 3,
      selectedFile: null,
    };
  },
  computed: {
    displayUrl() {
      return window.location.origin + '/display';
    },
  },
  methods: {
    saveConfig() {
      const sec = Math.max(1, Math.min(60, Number(this.intervalSeconds) || 3));
      Inertia.put(route('admin.display-screen.config.update'), { interval_seconds: sec });
    },
    onFileSelect(event) {
      this.selectedFile = event.target.files?.[0] || null;
    },
    uploadImage() {
      if (!this.selectedFile) return;
      const formData = new FormData();
      formData.append('image', this.selectedFile);
      Inertia.post(route('admin.display-screen.slides.store'), formData, {
        forceFormData: true,
        onSuccess: () => {
          this.selectedFile = null;
          if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },
      });
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
