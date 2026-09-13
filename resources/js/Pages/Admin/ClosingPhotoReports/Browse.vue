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
                عرض فقط — يظهر كل البنود وحالة كل بند (مرفوع / متبقي) لكل فرع.
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
              <a
                v-if="item.photo"
                :href="item.photo.url"
                target="_blank"
                rel="noopener"
                class="block"
              >
                <img :src="item.photo.url" :alt="item.title" class="w-full h-36 object-cover" />
              </a>
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
    statusBadgeClass(row) {
      if (row.is_complete) return 'bg-green-100 text-green-800';
      if (row.uploaded_total > 0) return 'bg-amber-100 text-amber-900';
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
  },
};
</script>
