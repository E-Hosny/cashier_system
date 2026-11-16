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
                افتراضياً يظهر «المحتاج» لآخر يوم مسجّل. يمكنك التبديل إلى «مش محتاج» أو اختيار يوم آخر.
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
                @click="applyFilters"
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

          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              class="rounded-full px-4 py-1.5 text-sm font-bold"
              :class="selectedStatus === 'needed' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-700'"
              @click="setStatus('needed')"
            >
              محتاج
            </button>
            <button
              type="button"
              class="rounded-full px-4 py-1.5 text-sm font-bold"
              :class="selectedStatus === 'not_needed' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800'"
              @click="setStatus('not_needed')"
            >
              مش محتاج
            </button>
          </div>

          <div class="flex flex-wrap gap-3 text-sm">
            <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-700">
              التاريخ: {{ report.business_date || '—' }}
            </span>
            <span
              class="rounded-full px-3 py-1 font-semibold"
              :class="selectedStatus === 'needed' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800'"
            >
              إجمالي {{ selectedStatus === 'needed' ? 'المحتاج' : 'مش محتاج' }}: {{ report.total_filtered || 0 }}
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
          لا توجد تسليمات نواقص لهذا اليوم.
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
              <span
                class="rounded-full px-2.5 py-1 text-xs font-bold"
                :class="selectedStatus === 'needed' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800'"
              >
                {{ closing.items_count || 0 }}
                {{ selectedStatus === 'needed' ? 'محتاج' : 'مش محتاج' }}
              </span>
            </div>

            <div v-if="!(closing.items || []).length" class="text-sm font-semibold" :class="selectedStatus === 'needed' ? 'text-emerald-700' : 'text-gray-600'">
              <template v-if="selectedStatus === 'needed'">لا توجد نواقص (كل البنود «مش محتاج»).</template>
              <template v-else>لا توجد بنود بحالة «مش محتاج».</template>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="group in groupedItems(closing.items)"
                :key="group.category"
              >
                <div class="text-xs font-bold text-gray-500 mb-1">{{ group.category }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div
                    v-for="item in group.items"
                    :key="item.id"
                    class="rounded-lg px-3 py-2"
                    :class="selectedStatus === 'needed' ? 'bg-red-50 text-red-900' : 'bg-emerald-50 text-emerald-900'"
                  >
                    <div class="text-sm font-semibold">{{ item.title }}</div>
                    <div class="text-xs mt-0.5 opacity-80">
                      {{ stockLabel(item) }}
                    </div>
                  </div>
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
      selectedStatus: this.report?.status || 'needed',
    };
  },
  watch: {
    report: {
      deep: true,
      handler(value) {
        this.selectedDate = value?.business_date || '';
        this.selectedStatus = value?.status || 'needed';
      },
    },
  },
  methods: {
    applyFilters() {
      router.get(route('admin.closing-photo-reports.shortages'), {
        business_date: this.selectedDate || undefined,
        status: this.selectedStatus || 'needed',
      }, { preserveState: true, replace: true });
    },
    goLatest() {
      router.get(route('admin.closing-photo-reports.shortages'), {
        status: this.selectedStatus || 'needed',
      }, { preserveState: true, replace: true });
    },
    jumpDate(date) {
      this.selectedDate = date;
      this.applyFilters();
    },
    setStatus(status) {
      this.selectedStatus = status;
      this.applyFilters();
    },
    groupedItems(items) {
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
    stockLabel(item) {
      if (item.branch_stock === null || item.branch_stock === undefined) {
        return 'مخزون الفرع: —';
      }
      const unit = item.branch_stock_unit ? ` ${item.branch_stock_unit}` : '';
      if (item.branch_stock_pieces !== null && item.branch_stock_pieces !== undefined) {
        return `مخزون الفرع: ${item.branch_stock_pieces} قطعة · ${item.branch_stock}${unit}`;
      }
      return `مخزون الفرع: ${item.branch_stock}${unit}`;
    },
  },
};
</script>
