<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    pulls: { type: Array, default: () => [] },
    selectedDate: { type: String, default: '' },
    maxBusinessDay: { type: String, default: '' },
    businessDayLabel: { type: String, default: '' },
    summaryByBranch: { type: Array, default: () => [] },
    totalPulls: { type: Number, default: 0 },
    hubBranches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ branch_id: '' }) },
});

const filterDate = ref(props.selectedDate || props.maxBusinessDay || '');
const branchFilter = ref(props.filters?.branch_id || '');

watch(
    () => props.selectedDate,
    (v) => {
        if (v) filterDate.value = v;
    }
);

watch(
    () => props.filters?.branch_id,
    (v) => {
        branchFilter.value = v || '';
    }
);

function applyFilters() {
    const params = { date: filterDate.value };
    if (branchFilter.value) {
        params.branch_id = branchFilter.value;
    }
    router.get(route('admin.raw-materials.branch-pulls-report'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout title="مسحوبات الفروع">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📋 مسحوبات الفروع — المواد الخام
            </h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-wrap gap-2 items-center justify-between">
                    <Link
                        :href="route('admin.raw-materials.index')"
                        class="px-4 py-2 rounded-lg bg-gray-500 hover:bg-gray-600 text-white font-semibold"
                    >
                        ⬅️ العودة للمواد الخام
                    </Link>
                </div>

                <div class="bg-white shadow-xl rounded-lg p-6">
                    <div class="flex flex-wrap items-end gap-4 mb-4">
                        <div>
                            <label for="report_date" class="block text-sm font-medium text-gray-700 mb-1">يوم العمل</label>
                            <input
                                id="report_date"
                                v-model="filterDate"
                                type="date"
                                class="border border-gray-300 rounded-lg p-2.5"
                                :max="maxBusinessDay"
                                @change="applyFilters"
                            />
                        </div>
                        <div>
                            <label for="branch_filter" class="block text-sm font-medium text-gray-700 mb-1">الفرع</label>
                            <select
                                id="branch_filter"
                                v-model="branchFilter"
                                class="border border-gray-300 rounded-lg p-2.5 min-w-[200px]"
                                @change="applyFilters"
                            >
                                <option value="">جميع الفروع</option>
                                <option v-for="b in hubBranches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>

                    <p v-if="businessDayLabel" class="text-sm text-blue-700 bg-blue-50 rounded-lg px-3 py-2 mb-4">
                        {{ businessDayLabel }}
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-green-700">{{ totalPulls }}</div>
                            <div class="text-sm text-green-900">إجمالي المسحوبات</div>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-blue-700">{{ summaryByBranch.length }}</div>
                            <div class="text-sm text-blue-900">فروع نشطة</div>
                        </div>
                        <div class="bg-slate-50 rounded-lg p-4 text-center">
                            <div class="text-sm font-medium text-slate-800">يوم العمل (7 ص → 7 ص)</div>
                            <div class="text-lg font-bold text-slate-700 mt-1">{{ selectedDate }}</div>
                        </div>
                    </div>

                    <div v-if="summaryByBranch.length" class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">ملخص حسب الفرع</h3>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="row in summaryByBranch"
                                :key="row.branch_name"
                                class="inline-flex items-center gap-2 bg-gray-100 border border-gray-200 rounded-full px-4 py-1.5 text-sm"
                            >
                                <span class="font-medium">{{ row.branch_name }}</span>
                                <span class="bg-green-600 text-white rounded-full px-2 py-0.5 text-xs font-bold">{{ row.pull_count }}</span>
                            </span>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-800 mb-3">تفاصيل المسحوبات</h3>
                    <div class="overflow-x-auto rounded-lg border">
                        <table class="w-full border-collapse text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-200 p-3 text-right">الوقت</th>
                                    <th class="border border-gray-200 p-3 text-right">الفرع</th>
                                    <th class="border border-gray-200 p-3 text-right">المادة</th>
                                    <th class="border border-gray-200 p-3 text-right">القطع</th>
                                    <th class="border border-gray-200 p-3 text-right">الكود</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="pulls.length === 0">
                                    <td colspan="5" class="text-center p-8 text-gray-500">
                                        لا توجد مسحوبات في يوم العمل المحدد.
                                    </td>
                                </tr>
                                <tr v-for="row in pulls" :key="row.id" class="hover:bg-gray-50">
                                    <td class="border border-gray-200 p-3 whitespace-nowrap">{{ row.received_at }}</td>
                                    <td class="border border-gray-200 p-3 font-medium">{{ row.branch_name }}</td>
                                    <td class="border border-gray-200 p-3">
                                        <div>{{ row.product_name }}</div>
                                        <ul v-if="row.lines?.length" class="text-xs text-gray-600 mt-1 space-y-0.5">
                                            <li v-for="(line, i) in row.lines" :key="i">
                                                {{ line.product_name }} — {{ line.piece_count }} {{ line.unit }}
                                            </li>
                                        </ul>
                                    </td>
                                    <td class="border border-gray-200 p-3">
                                        <template v-if="row.piece_count != null">{{ row.piece_count }} {{ row.unit }}</template>
                                        <span v-else class="text-gray-500">—</span>
                                    </td>
                                    <td class="border border-gray-200 p-3 font-mono text-xs break-all">{{ row.label_code }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
