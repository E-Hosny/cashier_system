<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    counts: { type: Object, required: true },
    hubBranches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ branch_id: '', status: '' }) },
});

const branchFilter = ref(props.filters.branch_id || '');
const statusFilter = ref(props.filters.status || '');
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

watch(
    () => props.filters,
    (f) => {
        branchFilter.value = f?.branch_id || '';
        statusFilter.value = f?.status || '';
    },
    { deep: true }
);

function applyFilters() {
    const params = {};
    if (branchFilter.value) params.branch_id = branchFilter.value;
    if (statusFilter.value) params.status = statusFilter.value;
    router.get(route('admin.raw-materials.inventory-counts.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatMoney(v) {
    return Number(v || 0).toFixed(2);
}

function statusClass(status) {
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'in_progress') return 'bg-amber-100 text-amber-800';
    return 'bg-gray-100 text-gray-700';
}

function cardBorderClass(status) {
    if (status === 'completed') return 'border-green-200';
    if (status === 'in_progress') return 'border-amber-300';
    return 'border-gray-200';
}

function netClass(v) {
    const n = Number(v || 0);
    if (n > 0.009) return 'text-green-700';
    if (n < -0.009) return 'text-red-700';
    return 'text-gray-700';
}

function progressPercent(row) {
    if (!row.items_count) return 0;
    return Math.round((Number(row.counted_items_count || 0) / Number(row.items_count)) * 100);
}
</script>

<template>
    <AppLayout title="تقارير الجرد">
        <template #header>
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">📊 تقارير جرد المواد الخام</h2>
        </template>

        <div class="py-4 sm:py-8" dir="rtl">
            <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4 sm:space-y-6">
                <div class="flex flex-wrap gap-2 items-center justify-between">
                    <Link
                        :href="route('admin.raw-materials.index')"
                        class="px-3 sm:px-4 py-2 rounded-lg bg-gray-500 hover:bg-gray-600 text-white text-sm sm:text-base font-semibold"
                    >
                        ⬅️ العودة
                    </Link>
                </div>

                <div
                    v-if="flashSuccess"
                    class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"
                >
                    {{ flashSuccess }}
                </div>

                <div class="bg-white shadow rounded-xl p-3 sm:p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4 sm:mb-5">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">الفرع</label>
                            <select
                                v-model="branchFilter"
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white"
                                @change="applyFilters"
                            >
                                <option value="">الكل</option>
                                <option v-for="b in hubBranches" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">الحالة</label>
                            <select
                                v-model="statusFilter"
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white"
                                @change="applyFilters"
                            >
                                <option value="">الكل</option>
                                <option value="in_progress">قيد التنفيذ</option>
                                <option value="completed">مكتمل ومُوازن</option>
                                <option value="cancelled">ملغي</option>
                            </select>
                        </div>
                    </div>

                    <!-- جدول ديسكتوب -->
                    <div class="hidden md:block overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="p-3 text-right">#</th>
                                    <th class="p-3 text-right">الفرع</th>
                                    <th class="p-3 text-right">الحالة</th>
                                    <th class="p-3 text-right">البدء</th>
                                    <th class="p-3 text-right">الإنهاء</th>
                                    <th class="p-3 text-right">التقدم</th>
                                    <th class="p-3 text-right">صافي فرق القيمة</th>
                                    <th class="p-3 text-center">عرض</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!counts.data?.length">
                                    <td colspan="8" class="p-8 text-center text-gray-500">لا توجد عمليات جرد بعد.</td>
                                </tr>
                                <tr
                                    v-for="row in counts.data"
                                    :key="row.id"
                                    class="border-t border-gray-100 hover:bg-slate-50"
                                >
                                    <td class="p-3 font-mono">{{ row.id }}</td>
                                    <td class="p-3 font-semibold">{{ row.branch_name }}</td>
                                    <td class="p-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold" :class="statusClass(row.status)">
                                            {{ row.status_label }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div>{{ row.started_at || '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ row.started_by_name }}</div>
                                    </td>
                                    <td class="p-3">
                                        <div>{{ row.completed_at || '—' }}</div>
                                        <div class="text-xs text-gray-500">{{ row.completed_by_name || '' }}</div>
                                    </td>
                                    <td class="p-3">{{ row.counted_items_count }} / {{ row.items_count }}</td>
                                    <td class="p-3 font-bold" :class="netClass(row.net_diff_value)">
                                        {{ formatMoney(row.net_diff_value) }}
                                    </td>
                                    <td class="p-3 text-center">
                                        <Link
                                            :href="route('admin.raw-materials.inventory-counts.show', row.id)"
                                            class="inline-block px-3 py-1.5 rounded-lg bg-slate-800 text-white text-xs font-semibold hover:bg-slate-900"
                                        >
                                            فتح
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- كروت الجوال -->
                    <div class="md:hidden space-y-3">
                        <div
                            v-if="!counts.data?.length"
                            class="rounded-2xl border border-gray-200 bg-slate-50 p-8 text-center text-gray-500"
                        >
                            لا توجد عمليات جرد بعد.
                        </div>

                        <article
                            v-for="row in counts.data"
                            :key="'card-' + row.id"
                            class="rounded-2xl border bg-white shadow-sm overflow-hidden"
                            :class="cardBorderClass(row.status)"
                        >
                            <div class="px-4 py-3 flex items-start justify-between gap-3 bg-slate-50/80">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs font-mono text-gray-500">#{{ row.id }}</span>
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold" :class="statusClass(row.status)">
                                            {{ row.status_label }}
                                        </span>
                                    </div>
                                    <h3 class="mt-1 font-bold text-gray-900 text-base leading-snug">
                                        {{ row.branch_name || 'فرع' }}
                                    </h3>
                                </div>
                            </div>

                            <div class="p-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
                                        <div class="text-[11px] text-gray-500">البدء</div>
                                        <div class="font-semibold text-gray-900 mt-0.5">{{ row.started_at || '—' }}</div>
                                        <div class="text-[11px] text-gray-500 mt-0.5 truncate">{{ row.started_by_name || '' }}</div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
                                        <div class="text-[11px] text-gray-500">الإنهاء</div>
                                        <div class="font-semibold text-gray-900 mt-0.5">{{ row.completed_at || '—' }}</div>
                                        <div class="text-[11px] text-gray-500 mt-0.5 truncate">{{ row.completed_by_name || '' }}</div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1.5">
                                        <span>التقدم</span>
                                        <span class="font-semibold">{{ row.counted_items_count }} / {{ row.items_count }} ({{ progressPercent(row) }}%)</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="row.status === 'completed' ? 'bg-green-600' : 'bg-amber-500'"
                                            :style="{ width: progressPercent(row) + '%' }"
                                        />
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-white px-3 py-2.5">
                                    <span class="text-xs text-gray-500">صافي فرق القيمة</span>
                                    <span class="font-extrabold text-base tabular-nums" :class="netClass(row.net_diff_value)">
                                        {{ formatMoney(row.net_diff_value) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-xs">
                                    <div class="rounded-lg bg-red-50 text-red-700 px-3 py-2 text-center">
                                        <div class="opacity-80">عجز</div>
                                        <div class="font-bold tabular-nums">{{ formatMoney(row.total_shortage_value) }}</div>
                                    </div>
                                    <div class="rounded-lg bg-green-50 text-green-700 px-3 py-2 text-center">
                                        <div class="opacity-80">زيادة</div>
                                        <div class="font-bold tabular-nums">{{ formatMoney(row.total_surplus_value) }}</div>
                                    </div>
                                </div>

                                <Link
                                    :href="route('admin.raw-materials.inventory-counts.show', row.id)"
                                    class="block w-full text-center px-4 py-3 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-sm active:scale-[0.99]"
                                >
                                    {{ row.status === 'in_progress' ? '▶️ متابعة الجرد' : '📄 فتح التقرير' }}
                                </Link>
                            </div>
                        </article>
                    </div>

                    <div v-if="counts.links?.length > 3" class="mt-4 flex flex-wrap gap-2 justify-center">
                        <Link
                            v-for="(link, idx) in counts.links"
                            :key="idx"
                            :href="link.url || '#'"
                            class="px-3 py-1.5 rounded-lg border text-sm"
                            :class="link.active ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-gray-700'"
                            v-html="link.label"
                            :preserve-scroll="true"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
