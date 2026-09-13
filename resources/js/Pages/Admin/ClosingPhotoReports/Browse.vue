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
                عرض فقط — بدون تصوير أو رفع. يمكنك التصفية حسب الفرع والتاريخ ونوع التقفيلة.
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
          لا توجد صور تقفيلة مطابقة للفلتر.
        </div>

        <div v-for="row in submissions" :key="row.id" class="bg-white shadow-xl sm:rounded-lg p-5 space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
              <div class="font-semibold text-gray-900 text-lg">
                {{ row.branch_name }} · {{ row.closing_type_label }}
              </div>
              <div class="text-sm text-gray-600">
                اليوم التشغيلي: {{ row.business_date }}
                · بواسطة: {{ row.submitted_by_name || '—' }}
                · الصور: {{ row.photos_count }}
              </div>
              <div v-if="row.completed_at" class="text-xs text-gray-500">اكتمل: {{ row.completed_at }}</div>
            </div>
            <span
              class="px-3 py-1 rounded-full text-xs font-semibold"
              :class="row.is_complete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900'"
            >
              {{ row.is_complete ? 'مكتمل' : 'غير مكتمل' }}
            </span>
          </div>

          <div v-if="!row.photos.length" class="text-sm text-gray-500">لا توجد صور مرفوعة بعد.</div>
          <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
            <a
              v-for="photo in row.photos"
              :key="photo.id"
              :href="photo.url"
              target="_blank"
              rel="noopener"
              class="block border border-gray-200 rounded-xl overflow-hidden bg-gray-50 hover:shadow-md transition"
            >
              <img :src="photo.url" :alt="photo.item_title" class="w-full h-36 object-cover" />
              <div class="p-2 space-y-0.5">
                <div class="text-xs font-medium text-gray-800 truncate" :title="photo.item_title">
                  {{ photo.item_title }}
                </div>
                <div class="text-[11px] text-gray-500">{{ photo.uploaded_at || '—' }}</div>
              </div>
            </a>
          </div>
        </div>
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
    };
  },
  methods: {
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
  },
};
</script>
