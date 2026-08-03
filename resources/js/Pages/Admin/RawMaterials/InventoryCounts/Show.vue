<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    inventoryCount: { type: Object, required: true },
    rawMaterialCategories: { type: Array, default: () => [] },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const search = ref('');
const categoryFilter = ref('');
const filterMode = ref('all'); // all | pending | counted | variance
const savingId = ref(null);
const localItems = ref([]);
const progress = reactive({
    items_count: props.inventoryCount.items_count,
    counted_items_count: props.inventoryCount.counted_items_count,
});
const draftPieces = reactive({});
const completeNotes = ref('');
const completing = ref(false);

function syncFromProps() {
    localItems.value = (props.inventoryCount.items || []).map((item) => ({ ...item }));
    progress.items_count = props.inventoryCount.items_count;
    progress.counted_items_count = props.inventoryCount.counted_items_count;
    for (const item of localItems.value) {
        draftPieces[item.id] =
            item.counted_pieces !== null && item.counted_pieces !== undefined
                ? String(item.counted_pieces)
                : '';
    }
}

syncFromProps();

watch(
    () => props.inventoryCount,
    () => syncFromProps(),
    { deep: true }
);

const filteredItems = computed(() => {
    const q = search.value.trim().toLowerCase();
    const categoryId = categoryFilter.value ? String(categoryFilter.value) : '';
    return localItems.value.filter((item) => {
        if (q && !(item.product_name || '').toLowerCase().includes(q)) {
            return false;
        }
        if (categoryId) {
            if (categoryId === 'none') {
                if (item.category_id) return false;
            } else if (String(item.category_id || '') !== categoryId) {
                return false;
            }
        }
        if (filterMode.value === 'pending') return !item.is_counted;
        if (filterMode.value === 'counted') return item.is_counted;
        if (filterMode.value === 'variance') {
            return item.is_counted && Math.abs(Number(item.diff_qty || 0)) > 0.0001;
        }
        return true;
    });
});

const liveTotals = computed(() => {
    let surplusValue = 0;
    let shortageValue = 0;
    let surplusPieces = 0;
    let shortagePieces = 0;
    for (const item of localItems.value) {
        if (!item.is_counted) continue;
        const diffPieces = Number(item.diff_pieces || 0);
        const diffValue = Number(item.diff_value || 0);
        if (diffPieces > 0.0001) {
            surplusPieces += diffPieces;
            surplusValue += diffValue;
        } else if (diffPieces < -0.0001) {
            shortagePieces += Math.abs(diffPieces);
            shortageValue += Math.abs(diffValue);
        }
    }
    return {
        surplusPieces,
        shortagePieces,
        surplusValue,
        shortageValue,
        netValue: surplusValue - shortageValue,
        pending: Math.max(0, progress.items_count - progress.counted_items_count),
        percent: progress.items_count
            ? Math.round((progress.counted_items_count / progress.items_count) * 100)
            : 0,
    };
});

function formatMoney(v) {
    return Number(v || 0).toFixed(2);
}

function formatQty(v) {
    const n = Number(v || 0);
    return Number.isInteger(n) ? String(n) : n.toFixed(2);
}

function diffClass(v) {
    const n = Number(v || 0);
    if (n > 0.0001) return 'text-green-700 bg-green-50';
    if (n < -0.0001) return 'text-red-700 bg-red-50';
    return 'text-gray-600 bg-gray-50';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
}

async function saveItem(item) {
    if (!props.inventoryCount.is_in_progress || savingId.value) return;

    const raw = draftPieces[item.id];
    if (raw === '' || raw === null || raw === undefined) {
        alert('أدخل الكمية الفعلية بالقطع');
        return;
    }

    const pieces = Number(raw);
    if (!Number.isFinite(pieces) || pieces < 0) {
        alert('كمية غير صحيحة');
        return;
    }

    savingId.value = item.id;
    try {
        const response = await fetch(
            route('admin.raw-materials.inventory-counts.items.update', [
                props.inventoryCount.id,
                item.id,
            ]),
            {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ counted_pieces: pieces }),
            }
        );
        const data = await response.json();
        if (!response.ok || !data.success) {
            alert(data.message || 'تعذر حفظ الكمية');
            return;
        }

        const idx = localItems.value.findIndex((i) => i.id === item.id);
        if (idx !== -1) {
            localItems.value[idx] = { ...data.item };
        }
        progress.items_count = data.progress.items_count;
        progress.counted_items_count = data.progress.counted_items_count;
        draftPieces[item.id] = String(data.item.counted_pieces ?? pieces);
    } catch (e) {
        console.error(e);
        alert('حدث خطأ في الاتصال');
    } finally {
        savingId.value = null;
    }
}

async function clearItem(item) {
    if (!props.inventoryCount.is_in_progress || savingId.value) return;
    if (!confirm('مسح عدّ هذه المادة؟')) return;

    savingId.value = item.id;
    try {
        const response = await fetch(
            route('admin.raw-materials.inventory-counts.items.clear', [
                props.inventoryCount.id,
                item.id,
            ]),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            }
        );
        const data = await response.json();
        if (!response.ok || !data.success) {
            alert(data.message || 'تعذر المسح');
            return;
        }
        const idx = localItems.value.findIndex((i) => i.id === item.id);
        if (idx !== -1) {
            localItems.value[idx] = { ...data.item };
        }
        progress.items_count = data.progress.items_count;
        progress.counted_items_count = data.progress.counted_items_count;
        draftPieces[item.id] = '';
    } catch (e) {
        console.error(e);
        alert('حدث خطأ في الاتصال');
    } finally {
        savingId.value = null;
    }
}

function copySystem(item) {
    draftPieces[item.id] = String(item.system_pieces ?? 0);
    saveItem(item);
}

function completeCount() {
    if (!props.inventoryCount.is_in_progress || completing.value) return;

    const pending = liveTotals.value.pending;
    let msg =
        'سيتم إنهاء الجرد وموازنة مخزون الفرع حسب الكميات التي أدخلتها.\n\n' +
        'بعد الإنهاء لا يمكن التعديل، ويُحفظ تقرير دائم.';
    if (pending > 0) {
        msg +=
            `\n\nتنبيه: يوجد ${pending} مادة لم تُعدّ بعد — ستُعتبر مطابقة لرصيد النظام (بدون تغيير).`;
    }
    if (!confirm(msg)) return;

    completing.value = true;
    router.post(
        route('admin.raw-materials.inventory-counts.complete', props.inventoryCount.id),
        { notes: completeNotes.value || null },
        {
            onFinish: () => {
                completing.value = false;
            },
        }
    );
}

function cancelCount() {
    if (!props.inventoryCount.is_in_progress) return;
    if (!confirm('إلغاء الجرد بالكامل دون تعديل المخزون؟')) return;
    router.post(route('admin.raw-materials.inventory-counts.cancel', props.inventoryCount.id));
}

function printReport() {
    window.print();
}
</script>

<template>
    <AppLayout :title="`جرد — ${inventoryCount.branch_name}`">
        <template #header>
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 leading-tight">
                📦 جرد فرع {{ inventoryCount.branch_name }}
            </h2>
        </template>

        <div class="pb-24 sm:pb-6 sm:py-6" dir="rtl">
            <div class="max-w-screen-2xl mx-auto px-3 sm:px-6 lg:px-8 space-y-3 sm:space-y-4">
                <!-- أزرار علوية -->
                <div class="flex flex-wrap gap-2 items-center justify-between no-print pt-3 sm:pt-0">
                    <div class="flex flex-wrap gap-2">
                        <Link
                            :href="route('admin.raw-materials.index', { view_scope: String(inventoryCount.branch_id), tab: 'materials' })"
                            class="px-3 py-2 rounded-lg bg-gray-500 hover:bg-gray-600 text-white text-sm font-semibold"
                        >
                            ⬅️ رجوع
                        </Link>
                        <Link
                            :href="route('admin.raw-materials.inventory-counts.index')"
                            class="px-3 py-2 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-sm font-semibold"
                        >
                            📊 التقارير
                        </Link>
                    </div>
                    <div class="hidden sm:flex flex-wrap gap-2">
                        <button
                            v-if="inventoryCount.is_completed"
                            type="button"
                            class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold"
                            @click="printReport"
                        >
                            🖨️ طباعة التقرير
                        </button>
                        <button
                            v-if="inventoryCount.is_in_progress"
                            type="button"
                            class="px-4 py-2 rounded-lg bg-red-100 text-red-800 border border-red-200 font-semibold"
                            @click="cancelCount"
                        >
                            إلغاء الجرد
                        </button>
                        <button
                            v-if="inventoryCount.is_in_progress"
                            type="button"
                            class="px-4 py-2 rounded-lg bg-green-700 hover:bg-green-800 text-white font-bold disabled:opacity-50"
                            :disabled="completing"
                            @click="completeCount"
                        >
                            ✅ إنهاء وموازنة
                        </button>
                    </div>
                    <button
                        v-if="inventoryCount.is_completed"
                        type="button"
                        class="sm:hidden px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold"
                        @click="printReport"
                    >
                        🖨️ طباعة
                    </button>
                </div>

                <div
                    v-if="flashSuccess"
                    class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm no-print"
                >
                    {{ flashSuccess }}
                </div>

                <!-- ملخص -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                        <div class="text-[11px] sm:text-xs text-gray-500">الحالة</div>
                        <div class="mt-0.5 font-bold text-sm sm:text-base text-gray-900 leading-snug">{{ inventoryCount.status_label }}</div>
                        <div class="text-[10px] sm:text-xs text-gray-500 mt-1">{{ inventoryCount.started_at }}</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                        <div class="text-[11px] sm:text-xs text-gray-500">التقدم</div>
                        <div class="mt-0.5 font-bold text-sm sm:text-base text-gray-900">
                            {{ progress.counted_items_count }}/{{ progress.items_count }}
                            <span class="text-xs text-gray-500">({{ liveTotals.percent }}%)</span>
                        </div>
                        <div class="mt-2 h-1.5 sm:h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-green-600 transition-all" :style="{ width: liveTotals.percent + '%' }" />
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-red-100 p-3 sm:p-4 shadow-sm">
                        <div class="text-[11px] sm:text-xs text-red-600">عجز</div>
                        <div class="mt-0.5 font-bold text-sm sm:text-base text-red-700">{{ formatMoney(liveTotals.shortageValue) }}</div>
                        <div class="text-[10px] sm:text-xs text-gray-500 mt-1">{{ formatQty(liveTotals.shortagePieces) }} قطعة</div>
                    </div>
                    <div class="bg-white rounded-xl border border-green-100 p-3 sm:p-4 shadow-sm">
                        <div class="text-[11px] sm:text-xs text-green-600">زيادة / صافي</div>
                        <div class="mt-0.5 font-bold text-sm sm:text-base text-green-700">{{ formatMoney(liveTotals.surplusValue) }}</div>
                        <div class="text-[10px] sm:text-xs text-gray-500 mt-1">صافي {{ formatMoney(liveTotals.netValue) }}</div>
                    </div>
                </div>

                <div v-if="inventoryCount.is_in_progress" class="hidden sm:block bg-amber-50 border border-amber-200 rounded-xl p-4 no-print">
                    <p class="text-sm text-amber-900 leading-relaxed">
                        أدخل الكمية <strong>الفعلية بالقطع</strong> لكل مادة ثم احفظ. النظام يحسب فرق الكمية والقيمة فوراً.
                        عند «إنهاء وموازنة» يُحدَّث مخزون الفرع ويُحفظ التقرير نهائياً.
                    </p>
                    <div class="mt-3">
                        <label class="block text-xs font-medium text-amber-900 mb-1">ملاحظات الإنهاء (اختياري)</label>
                        <textarea
                            v-model="completeNotes"
                            rows="2"
                            class="w-full border border-amber-200 rounded-lg p-2 text-sm bg-white"
                            placeholder="مثال: جرد نهاية الشهر — فرع ..."
                        />
                    </div>
                </div>

                <div
                    v-if="inventoryCount.is_completed && inventoryCount.notes"
                    class="bg-slate-50 border border-slate-200 rounded-xl p-3 sm:p-4 text-sm text-slate-700"
                >
                    <strong>ملاحظات:</strong> {{ inventoryCount.notes }}
                    <span v-if="inventoryCount.completed_at" class="text-gray-500 block sm:inline mt-1 sm:mt-0">
                        — أُنهي {{ inventoryCount.completed_at }} بواسطة {{ inventoryCount.completed_by_name }}
                    </span>
                </div>

                <!-- فلاتر -->
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 space-y-3 no-print sticky top-0 z-20 shadow-sm md:static md:shadow-none">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-stretch">
                        <div class="md:col-span-8">
                            <label class="hidden md:block text-xs font-medium text-gray-600 mb-1">بحث</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="بحث باسم المادة..."
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm sm:text-base bg-white focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            />
                        </div>
                        <div class="md:col-span-4">
                            <label class="hidden md:block text-xs font-medium text-gray-600 mb-1">الفئة</label>
                            <select
                                v-model="categoryFilter"
                                class="block w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm sm:text-base bg-white font-medium focus:outline-none focus:ring-2 focus:ring-slate-400 focus:border-slate-400"
                            >
                                <option value="">كل الفئات</option>
                                <option
                                    v-for="c in rawMaterialCategories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >
                                    {{ c.name }}
                                </option>
                                <option value="none">بدون فئة</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="opt in [
                                { id: 'all', label: 'الكل' },
                                { id: 'pending', label: 'غير معدود' },
                                { id: 'counted', label: 'تم العد' },
                                { id: 'variance', label: 'فروقات' },
                            ]"
                            :key="opt.id"
                            type="button"
                            class="px-3.5 py-2 rounded-lg text-sm font-semibold border"
                            :class="filterMode === opt.id ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            @click="filterMode = opt.id"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 md:hidden">
                        ظاهر {{ filteredItems.length }} من {{ localItems.length }} مادة
                        <span v-if="liveTotals.pending"> · متبقي {{ liveTotals.pending }}</span>
                    </p>
                </div>

                <!-- جدول ديسكتوب فقط -->
                <div class="hidden md:block bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="p-3 text-right">المادة</th>
                                    <th class="p-3 text-center">رصيد النظام</th>
                                    <th class="p-3 text-center">الفعلي (قطع)</th>
                                    <th class="p-3 text-center">فرق الكمية</th>
                                    <th class="p-3 text-center">قيمة النظام</th>
                                    <th class="p-3 text-center">فرق القيمة</th>
                                    <th v-if="inventoryCount.is_in_progress" class="p-3 text-center no-print">إجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!filteredItems.length">
                                    <td :colspan="inventoryCount.is_in_progress ? 7 : 6" class="p-8 text-center text-gray-500">
                                        لا توجد مواد مطابقة للفلتر.
                                    </td>
                                </tr>
                                <tr
                                    v-for="item in filteredItems"
                                    :key="item.id"
                                    class="border-t border-gray-100"
                                    :class="item.is_counted ? '' : 'bg-amber-50/40'"
                                >
                                    <td class="p-3">
                                        <div class="font-bold text-gray-900">{{ item.product_name }}</div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <span v-if="item.category_name" class="text-indigo-700 font-medium">{{ item.category_name }} · </span>
                                            {{ item.unit || 'قطعة' }}
                                            <span v-if="item.consume_unit"> · {{ item.consume_unit }}</span>
                                            <span v-if="item.is_counted" class="mr-2 text-green-700 font-semibold">✓ معدود</span>
                                        </div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <div class="font-semibold">{{ formatQty(item.system_pieces) }}</div>
                                        <div class="text-[11px] text-gray-500">
                                            {{ formatQty(item.system_qty) }} {{ item.consume_unit || '' }}
                                        </div>
                                    </td>
                                    <td class="p-3 text-center">
                                        <template v-if="inventoryCount.is_in_progress">
                                            <input
                                                v-model="draftPieces[item.id]"
                                                type="number"
                                                inputmode="decimal"
                                                min="0"
                                                step="any"
                                                class="w-28 mx-auto border rounded-lg p-2 text-center font-semibold no-print"
                                                :disabled="savingId === item.id"
                                                @keydown.enter.prevent="saveItem(item)"
                                            />
                                        </template>
                                        <template v-else>
                                            <div class="font-bold">{{ formatQty(item.counted_pieces) }}</div>
                                            <div class="text-[11px] text-gray-500">
                                                {{ formatQty(item.counted_qty) }} {{ item.consume_unit || '' }}
                                            </div>
                                        </template>
                                    </td>
                                    <td class="p-3 text-center">
                                        <span
                                            v-if="item.is_counted"
                                            class="inline-block px-2 py-1 rounded-lg font-bold text-xs"
                                            :class="diffClass(item.diff_pieces)"
                                        >
                                            {{ item.diff_pieces > 0 ? '+' : '' }}{{ formatQty(item.diff_pieces) }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td class="p-3 text-center">{{ formatMoney(item.system_value) }}</td>
                                    <td class="p-3 text-center">
                                        <span
                                            v-if="item.is_counted"
                                            class="inline-block px-2 py-1 rounded-lg font-bold text-xs"
                                            :class="diffClass(item.diff_value)"
                                        >
                                            {{ item.diff_value > 0 ? '+' : '' }}{{ formatMoney(item.diff_value) }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                    <td v-if="inventoryCount.is_in_progress" class="p-3 text-center no-print whitespace-nowrap">
                                        <button
                                            type="button"
                                            class="px-2.5 py-1.5 rounded-lg bg-green-700 text-white text-xs font-bold disabled:opacity-50"
                                            :disabled="savingId === item.id"
                                            @click="saveItem(item)"
                                        >
                                            حفظ
                                        </button>
                                        <button
                                            type="button"
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold mr-1"
                                            :disabled="savingId === item.id"
                                            title="اعتبار الفعلي = النظام"
                                            @click="copySystem(item)"
                                        >
                                            مطابق
                                        </button>
                                        <button
                                            v-if="item.is_counted"
                                            type="button"
                                            class="px-2.5 py-1.5 rounded-lg bg-red-50 text-red-700 text-xs font-semibold mr-1"
                                            :disabled="savingId === item.id"
                                            @click="clearItem(item)"
                                        >
                                            مسح
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- كروت الجوال -->
                <div class="md:hidden space-y-3 no-print">
                    <div
                        v-if="!filteredItems.length"
                        class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-gray-500"
                    >
                        لا توجد مواد مطابقة للفلتر.
                    </div>

                    <article
                        v-for="item in filteredItems"
                        :key="'m-' + item.id"
                        class="bg-white rounded-2xl border shadow-sm overflow-hidden"
                        :class="item.is_counted
                            ? (Math.abs(Number(item.diff_qty || 0)) > 0.0001 ? 'border-amber-300' : 'border-green-200')
                            : 'border-slate-200'"
                    >
                        <div
                            class="px-4 py-3 flex items-start justify-between gap-3"
                            :class="item.is_counted ? 'bg-slate-50' : 'bg-amber-50'"
                        >
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900 text-base leading-snug">{{ item.product_name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    <span
                                        v-if="item.category_name"
                                        class="inline-flex text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800"
                                    >
                                        {{ item.category_name }}
                                    </span>
                                    <span class="text-[11px] text-gray-500">{{ item.unit || 'قطعة' }}</span>
                                </div>
                            </div>
                            <span
                                class="shrink-0 text-[11px] font-bold px-2 py-1 rounded-full"
                                :class="item.is_counted ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ item.is_counted ? '✓ معدود' : 'بانتظار' }}
                            </span>
                        </div>

                        <div class="p-4 space-y-3">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-slate-50 border border-slate-100 p-3">
                                    <div class="text-[11px] text-gray-500 mb-1">رصيد النظام</div>
                                    <div class="text-lg font-extrabold text-slate-800 tabular-nums">{{ formatQty(item.system_pieces) }}</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">قطعة · {{ formatMoney(item.system_value) }} ج</div>
                                </div>
                                <div class="rounded-xl border p-3" :class="item.is_counted ? 'bg-emerald-50 border-emerald-100' : 'bg-white border-dashed border-gray-300'">
                                    <div class="text-[11px] text-gray-500 mb-1">الفعلي</div>
                                    <div v-if="item.is_counted" class="text-lg font-extrabold text-emerald-800 tabular-nums">
                                        {{ formatQty(item.counted_pieces) }}
                                    </div>
                                    <div v-else class="text-lg font-bold text-gray-300">—</div>
                                    <div class="text-[11px] text-gray-500 mt-0.5">
                                        <template v-if="item.is_counted">قطعة · {{ formatMoney(item.counted_value) }} ج</template>
                                        <template v-else>لم يُدخل بعد</template>
                                    </div>
                                </div>
                            </div>

                            <div v-if="item.is_counted" class="flex gap-2">
                                <div
                                    class="flex-1 rounded-xl px-3 py-2 text-center"
                                    :class="diffClass(item.diff_pieces)"
                                >
                                    <div class="text-[10px] opacity-80">فرق الكمية</div>
                                    <div class="font-extrabold text-sm tabular-nums">
                                        {{ item.diff_pieces > 0 ? '+' : '' }}{{ formatQty(item.diff_pieces) }}
                                    </div>
                                </div>
                                <div
                                    class="flex-1 rounded-xl px-3 py-2 text-center"
                                    :class="diffClass(item.diff_value)"
                                >
                                    <div class="text-[10px] opacity-80">فرق القيمة</div>
                                    <div class="font-extrabold text-sm tabular-nums">
                                        {{ item.diff_value > 0 ? '+' : '' }}{{ formatMoney(item.diff_value) }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="inventoryCount.is_in_progress" class="space-y-2 pt-1">
                                <label class="block text-sm font-semibold text-gray-700">الكمية الفعلية (قطع)</label>
                                <input
                                    v-model="draftPieces[item.id]"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="any"
                                    class="w-full border-2 border-slate-200 focus:border-green-600 rounded-xl p-3.5 text-center text-xl font-extrabold tabular-nums"
                                    :disabled="savingId === item.id"
                                    @keydown.enter.prevent="saveItem(item)"
                                />
                                <div class="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        class="col-span-2 px-4 py-3 rounded-xl bg-green-700 text-white text-base font-bold disabled:opacity-50 active:scale-[0.98]"
                                        :disabled="savingId === item.id"
                                        @click="saveItem(item)"
                                    >
                                        {{ savingId === item.id ? 'جاري الحفظ...' : '💾 حفظ العد' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="px-3 py-2.5 rounded-xl bg-slate-100 text-slate-800 text-sm font-semibold disabled:opacity-50"
                                        :disabled="savingId === item.id"
                                        @click="copySystem(item)"
                                    >
                                        مطابق للنظام
                                    </button>
                                    <button
                                        v-if="item.is_counted"
                                        type="button"
                                        class="px-3 py-2.5 rounded-xl bg-red-50 text-red-700 text-sm font-semibold disabled:opacity-50"
                                        :disabled="savingId === item.id"
                                        @click="clearItem(item)"
                                    >
                                        مسح العد
                                    </button>
                                    <div v-else class="px-3 py-2.5 rounded-xl bg-transparent" />
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- شريط سفلي ثابت للجوال -->
        <div
            v-if="inventoryCount.is_in_progress"
            class="md:hidden fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur no-print"
            style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
        >
            <div class="px-3 pt-2 space-y-2">
                <div class="flex items-center justify-between text-xs text-gray-600">
                    <span>متبقي {{ liveTotals.pending }} مادة</span>
                    <span>صافي الفرق {{ formatMoney(liveTotals.netValue) }}</span>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="px-3 py-3 rounded-xl bg-red-50 text-red-700 text-sm font-semibold border border-red-100"
                        @click="cancelCount"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        class="flex-1 px-4 py-3 rounded-xl bg-green-700 text-white text-base font-bold disabled:opacity-50 shadow-lg shadow-green-900/20"
                        :disabled="completing"
                        @click="completeCount"
                    >
                        ✅ إنهاء وموازنة
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
