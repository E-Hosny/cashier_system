<template>
  <AppLayout title="النواقص">
    <template #header>
      <div class="flex items-center gap-3">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">النواقص</h2>
      </div>
    </template>

    <div class="py-8" dir="rtl">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <div v-if="!enabled" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          خاصية النواقص غير مفعّلة حالياً من إعدادات صور التقفيلة.
        </div>

        <div class="bg-white shadow-xl sm:rounded-lg p-5 space-y-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 class="text-lg font-bold text-gray-900">نواقص الفروع</h3>
              <p class="text-sm text-gray-600">
                افتراضياً يظهر آخر يوم فيه نواقص مسجّلة. يمكنك اختيار يوم آخر.
              </p>
            </div>
            <div class="flex flex-wrap gap-2 items-end">
              <div>
                <label class="block text-xs text-gray-500 mb-1">اليوم التشغيلي</label>
                <input v-model="selectedDate" type="date" class="rounded-lg border-gray-300 text-sm" />
              </div>
              <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white"
                @click="applyDate"
              >
                عرض
              </button>
              <button
                v-if="report.latest_date"
                type="button"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800"
                @click="goLatest"
              >
                آخر يوم
              </button>
            </div>
          </div>

          <div class="flex flex-wrap gap-3 text-sm">
            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700">
              التاريخ: {{ report.business_date || '—' }}
            </span>
            <span class="rounded-full bg-red-50 px-3 py-1 font-semibold text-red-700">
              إجمالي المحتاج: {{ report.total_needed || 0 }}
            </span>
          </div>

          <div v-if="(report.available_dates || []).length" class="flex flex-wrap gap-2">
            <button
              v-for="date in report.available_dates.slice(0, 12)"
              :key="date"
              type="button"
              class="rounded-full px-3 py-1 text-xs font-semibold"
              :class="date === report.business_date ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'"
              @click="jumpDate(date)"
            >
              {{ date }}
            </button>
          </div>
        </div>

        <div v-if="!(report.branches || []).length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
          لا توجد نواقص مسجّلة لهذا اليوم.
        </div>

        <article
          v-for="branch in report.branches || []"
          :key="branch.branch_id"
          class="rounded-2xl bg-white p-5 shadow-sm space-y-4"
        >
          <h4 class="text-lg font-bold text-gray-900">{{ branch.branch_name }}</h4>

          <div
            v-for="closing in branch.closings"
            :key="closing.submission_id"
            class="rounded-xl border border-gray-200 p-4 space-y-3"
          >
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <div class="font-semibold text-gray-900">{{ closing.closing_type_label }}</div>
                <div class="text-xs text-gray-500">{{ closing.completed_at || '' }}</div>
              </div>
              <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-700">
                {{ closing.needed_count }} محتاج
              </span>
            </div>

            <div v-if="!closing.needed_items.length" class="text-sm text-emerald-700 font-semibold">
              لا توجد نواقص (كل البنود «مش محتاج»).
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="group in groupedNeeded(closing.needed_items)"
                :key="group.category"
              >
                <div class="text-xs font-bold text-gray-500 mb-1">{{ group.category }}</div>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="item in group.items"
                    :key="item.id"
                    class="rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-800"
                  >
                    {{ item.title }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </article>
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
    report: { type: Object, required: true },
    enabled: { type: Boolean, default: false },
    typeLabels: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      selectedDate: this.report?.business_date || '',
    };
  },
  watch: {
    report: {
      deep: true,
      handler(value) {
        this.selectedDate = value?.business_date || '';
      },
    },
  },
  methods: {
    applyDate() {
      router.get(route('admin.closing-photo-reports.shortages'), {
        business_date: this.selectedDate || undefined,
      }, { preserveState: true, replace: true });
    },
    goLatest() {
      router.get(route('admin.closing-photo-reports.shortages'), {}, { preserveState: true, replace: true });
    },
    jumpDate(date) {
      this.selectedDate = date;
      this.applyDate();
    },
    groupedNeeded(items) {
      const map = {};
      (items || []).forEach((item) => {
        const category = item.category_name || 'بدون فئة';
        if (!map[category]) map[category] = [];
        map[category].push(item);
      });
      return Object.keys(map).sort().map((category) => ({
        category,
        items: map[category],
      }));
    },
  },
};
</script>
