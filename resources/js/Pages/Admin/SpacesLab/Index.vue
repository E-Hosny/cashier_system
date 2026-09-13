<template>
  <AppLayout title="اختبار DigitalOcean Spaces">
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        اختبار تخزين Spaces
      </h2>
    </template>

    <div class="py-10" dir="rtl">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">معمل الملفات على Spaces</h3>
              <p class="text-sm text-gray-600">
                رفع / استبدال / عرض / حذف داخل المجلد
                <code class="bg-gray-100 px-1 rounded">{{ config.prefix }}</code>
                على الديسك
                <code class="bg-gray-100 px-1 rounded">{{ config.disk }}</code>
              </p>
            </div>
            <span
              class="inline-flex self-start px-3 py-1 rounded-full text-xs font-semibold"
              :class="diskConfigured ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
            >
              {{ diskConfigured ? 'الإعدادات مكتملة' : 'إعدادات ناقصة' }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm">
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-slate-500">Bucket</div>
              <div class="font-medium break-all">{{ config.bucket || '—' }}</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-slate-500">Region</div>
              <div class="font-medium">{{ config.region || '—' }}</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-slate-500">Endpoint</div>
              <div class="font-medium break-all">{{ config.endpoint || '—' }}</div>
            </div>
            <div class="bg-slate-50 rounded-lg p-3">
              <div class="text-slate-500">Public URL</div>
              <div class="font-medium break-all">{{ config.url || '—' }}</div>
            </div>
          </div>

          <div v-if="flashSuccess" class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">
            {{ flashSuccess }}
          </div>
          <div v-if="error || formError" class="rounded-lg bg-red-50 text-red-800 px-4 py-3 text-sm">
            {{ error || formError }}
          </div>
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
          <h4 class="font-semibold text-gray-900">رفع ملف جديد</h4>
          <form class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end" @submit.prevent="upload">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">الملف</label>
              <input
                ref="fileInput"
                type="file"
                class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                @change="onFileChange"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">اسم اختياري</label>
              <input
                v-model="uploadName"
                type="text"
                class="w-full rounded-lg border-gray-300 text-sm"
                placeholder="مثلاً logo-test"
              />
            </div>
            <button
              type="submit"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
              :disabled="loading || !selectedFile"
            >
              {{ loading ? 'جاري الرفع...' : 'رفع إلى Spaces' }}
            </button>
          </form>
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-6">
          <div class="flex items-center justify-between mb-4">
            <h4 class="font-semibold text-gray-900">الملفات ({{ files.length }})</h4>
            <button
              type="button"
              class="text-sm text-indigo-700 hover:text-indigo-900"
              :disabled="loading"
              @click="reload"
            >
              تحديث القائمة
            </button>
          </div>

          <div v-if="files.length === 0" class="text-center py-10 text-gray-500 text-sm">
            لا توجد ملفات في المجلد التجريبي بعد.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="file in files"
              :key="file.path"
              class="border border-gray-200 rounded-xl p-4 space-y-3"
            >
              <div class="flex gap-3">
                <div class="w-24 h-24 shrink-0 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center">
                  <img
                    v-if="file.is_image"
                    :src="file.url"
                    :alt="file.name"
                    class="w-full h-full object-cover"
                  />
                  <span v-else class="text-xs text-gray-500 px-2 text-center">ملف</span>
                </div>
                <div class="min-w-0 flex-1 space-y-1 text-sm">
                  <div class="font-semibold text-gray-900 truncate" :title="file.name">{{ file.name }}</div>
                  <div class="text-gray-500 truncate" :title="file.path">{{ file.path }}</div>
                  <div class="text-gray-600">{{ file.size_label }} · {{ file.mime || 'unknown' }}</div>
                  <div class="text-gray-500">{{ file.last_modified || '—' }}</div>
                  <a
                    :href="file.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-block text-indigo-600 hover:underline break-all"
                  >
                    فتح الرابط
                  </a>
                </div>
              </div>

              <div class="flex flex-wrap gap-2">
                <label class="inline-flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg border border-amber-300 text-amber-900 bg-amber-50 cursor-pointer hover:bg-amber-100">
                  استبدال
                  <input
                    type="file"
                    class="hidden"
                    :disabled="loading"
                    @change="(e) => replaceFile(file, e)"
                  />
                </label>
                <button
                  type="button"
                  class="text-xs px-3 py-1.5 rounded-lg border border-red-300 text-red-800 bg-red-50 hover:bg-red-100 disabled:opacity-50"
                  :disabled="loading"
                  @click="removeFile(file)"
                >
                  حذف
                </button>
              </div>
            </div>
          </div>
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
    files: { type: Array, default: () => [] },
    diskConfigured: { type: Boolean, default: false },
    error: { type: String, default: null },
    config: { type: Object, required: true },
  },
  data() {
    return {
      loading: false,
      selectedFile: null,
      uploadName: '',
    };
  },
  computed: {
    flashSuccess() {
      return usePage().props.flash?.success || null;
    },
    formError() {
      const errors = usePage().props.errors || {};
      return errors.file || Object.values(errors)[0] || null;
    },
  },
  methods: {
    onFileChange(e) {
      this.selectedFile = e.target.files?.[0] || null;
    },
    reload() {
      router.reload({ preserveScroll: true });
    },
    upload() {
      if (!this.selectedFile) return;

      this.loading = true;
      router.post(
        route('admin.spaces-lab.store'),
        {
          file: this.selectedFile,
          name: this.uploadName || null,
        },
        {
          forceFormData: true,
          preserveScroll: true,
          onFinish: () => {
            this.loading = false;
            this.selectedFile = null;
            this.uploadName = '';
            if (this.$refs.fileInput) {
              this.$refs.fileInput.value = '';
            }
          },
        }
      );
    },
    replaceFile(file, event) {
      const next = event.target.files?.[0];
      event.target.value = '';
      if (!next) return;

      if (!confirm(`استبدال الملف «${file.name}»؟`)) {
        return;
      }

      this.loading = true;
      router.post(
        route('admin.spaces-lab.update'),
        {
          path: file.path,
          file: next,
          _method: 'put',
        },
        {
          forceFormData: true,
          preserveScroll: true,
          onFinish: () => {
            this.loading = false;
          },
        }
      );
    },
    removeFile(file) {
      if (!confirm(`حذف الملف «${file.name}» من Spaces؟`)) {
        return;
      }

      this.loading = true;
      router.delete(route('admin.spaces-lab.destroy'), {
        data: { path: file.path },
        preserveScroll: true,
        onFinish: () => {
          this.loading = false;
        },
      });
    },
  },
};
</script>
