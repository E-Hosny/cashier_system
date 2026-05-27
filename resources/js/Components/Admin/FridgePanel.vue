<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import JsBarcode from 'jsbarcode';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { translateSize } from '@/utils/productSizes';

const props = defineProps({
    fridge: { type: Object, required: true },
    isCentralView: { type: Boolean, default: true },
    canManage: { type: Boolean, default: false },
    viewScope: { type: String, default: 'central' },
});

const page = usePage();
const configs = ref([...(props.fridge?.configs || [])]);
const showConfigForm = ref(false);
const editingConfig = ref(null);
const printModal = ref({ open: false, configId: null, labelId: null, productName: '', unit_count: 1, label_code: '', created: false });
const barcodeSvg = ref(null);

/** @type {import('vue').Ref<Record<number, { selected: boolean, unit_count: number }>>} */
const rowSelection = ref({});

const combinedModal = ref({
    open: false,
    created: false,
    label_code: '',
    labelId: null,
    resultLines: [],
});
const combinedBarcodeSvg = ref(null);

const selectedRows = computed(() =>
    configs.value.filter((c) => rowSelection.value[c.id]?.selected)
);

const allRowsSelected = computed(() => {
    if (!configs.value.length) return false;
    return configs.value.every((c) => rowSelection.value[c.id]?.selected);
});

function initRowSelection() {
    const next = { ...rowSelection.value };
    configs.value.forEach((c) => {
        if (!next[c.id]) {
            next[c.id] = { selected: false, unit_count: 1 };
        }
    });
    rowSelection.value = next;
}

watch(configs, () => initRowSelection(), { immediate: true });

function toggleSelectAll() {
    const select = !allRowsSelected.value;
    const next = { ...rowSelection.value };
    configs.value.forEach((c) => {
        next[c.id] = { ...next[c.id], selected: select, unit_count: next[c.id]?.unit_count ?? 1 };
    });
    rowSelection.value = next;
}

function openCombinedModal() {
    if (selectedRows.value.length < 2) {
        alert('اختر منتجين على الأقل من الجدول (مربع الاختيار).');
        return;
    }
    combinedModal.value = { open: true, created: false, label_code: '', labelId: null, resultLines: [] };
}

function submitCombinedPrint() {
    const items = selectedRows.value.map((c) => ({
        fridge_product_config_id: c.id,
        unit_count: parseFloat(rowSelection.value[c.id]?.unit_count) || 1,
    }));

    window.axios
        .post(route('admin.fridge.combined-labels.store'), { items })
        .then((res) => {
            const d = res.data || {};
            combinedModal.value.created = true;
            combinedModal.value.label_code = d.label_code || '';
            combinedModal.value.labelId = d.id || null;
            combinedModal.value.resultLines = d.items || [];

            items.forEach((item) => {
                const c = configs.value.find((x) => x.id === item.fridge_product_config_id);
                if (c) {
                    c.pending_units = (parseFloat(c.pending_units) || 0) + item.unit_count;
                }
            });

            const next = { ...rowSelection.value };
            selectedRows.value.forEach((c) => {
                next[c.id] = { selected: false, unit_count: 1 };
            });
            rowSelection.value = next;

            nextTick(() => renderBarcode(combinedBarcodeSvg.value, combinedModal.value.label_code));
        })
        .catch((err) => alert(err?.response?.data?.message || 'حدث خطأ.'));
}

const deductModes = [
    { value: 'none', label: 'بدون سحب مقادير' },
    { value: 'all', label: 'كل المقادير' },
    { value: 'custom', label: 'مقادير محددة' },
];

const selectedCategoryId = ref('');

const configForm = useForm({
    product_id: '',
    size: '',
    deduct_on_sale: 'none',
    ingredient_rules: [],
});

watch(() => props.fridge?.configs, (v) => {
    if (v) configs.value = [...v];
}, { deep: true });

const selectedProduct = computed(() =>
    (props.fridge?.finishedProducts || []).find((p) => String(p.id) === String(configForm.product_id))
);

const categorySelectOptions = computed(() =>
    (props.fridge?.categories || []).map((c) => ({
        value: c.id,
        label: c.name,
    }))
);

const productsInCategory = computed(() => {
    if (!selectedCategoryId.value) {
        return [];
    }
    return (props.fridge?.finishedProducts || []).filter(
        (p) => String(p.category_id) === String(selectedCategoryId.value)
    );
});

const productSelectOptions = computed(() =>
    productsInCategory.value.map((p) => ({
        value: p.id,
        label: p.name,
    }))
);

const sizeSelectOptions = computed(() =>
    (selectedProduct.value?.sizes || []).map((s) => ({
        value: s,
        label: translateSize(s),
    }))
);

async function loadIngredients() {
    if (!configForm.product_id) {
        configForm.ingredient_rules = [];
        return;
    }
    const res = await window.axios.get(route('admin.fridge.ingredients'), {
        params: { product_id: configForm.product_id, size: configForm.size || '' },
    });
    const list = res.data?.ingredients || [];
    const prev = {};
    configForm.ingredient_rules.forEach((r) => {
        prev[r.raw_material_id] = r;
    });
    configForm.ingredient_rules = list.map((ing) => ({
        raw_material_id: ing.raw_material_id,
        name: ing.name,
        quantity_consumed: ing.quantity_consumed,
        deduct_on_sale: !!prev[ing.raw_material_id]?.deduct_on_sale,
    }));
}

watch(selectedCategoryId, () => {
    if (!editingConfig.value) {
        configForm.product_id = '';
        configForm.size = '';
        configForm.ingredient_rules = [];
    }
});

watch(
    () => configForm.product_id,
    () => {
        if (!editingConfig.value && selectedProduct.value) {
            const sizes = selectedProduct.value.sizes || [];
            configForm.size = sizes.length ? sizes[0] : '';
        }
    }
);

watch([() => configForm.product_id, () => configForm.size], () => {
    if (showConfigForm.value && configForm.product_id) {
        loadIngredients();
    }
});

watch(
    () => configForm.deduct_on_sale,
    (mode) => {
        if (mode === 'custom' && configForm.product_id) {
            loadIngredients();
        }
    }
);

function openNewConfig() {
    editingConfig.value = null;
    selectedCategoryId.value = '';
    configForm.reset();
    configForm.deduct_on_sale = 'none';
    configForm.ingredient_rules = [];
    showConfigForm.value = true;
}

async function openEditConfig(cfg) {
    editingConfig.value = cfg;
    const product = (props.fridge?.finishedProducts || []).find((p) => p.id === cfg.product_id);
    selectedCategoryId.value = product?.category_id ? String(product.category_id) : '';
    configForm.product_id = cfg.product_id;
    configForm.size = cfg.size || '';
    configForm.deduct_on_sale = cfg.deduct_on_sale;
    showConfigForm.value = true;

    if (cfg.deduct_on_sale === 'custom') {
        await loadIngredients();
        const existing = {};
        (cfg.ingredient_rules || []).forEach((r) => {
            existing[r.raw_material_id] = r;
        });
        configForm.ingredient_rules = configForm.ingredient_rules.map((ing) => ({
            ...ing,
            deduct_on_sale: !!existing[ing.raw_material_id]?.deduct_on_sale,
        }));
    } else {
        configForm.ingredient_rules = [];
    }
}

function buildIngredientRulesPayload() {
    if (configForm.deduct_on_sale !== 'custom') {
        return [];
    }
    return configForm.ingredient_rules
        .filter((r) => r.deduct_on_sale)
        .map((r) => ({
            raw_material_id: r.raw_material_id,
            deduct_on_sale: true,
        }));
}

function submitConfig() {
    if (!editingConfig.value && !selectedCategoryId.value) {
        alert('اختر الفئة أولاً.');
        return;
    }
    if (!editingConfig.value && !configForm.product_id) {
        alert('اختر المنتج.');
        return;
    }
    if (!editingConfig.value && sizeSelectOptions.value.length && !configForm.size) {
        alert('اختر المقاس.');
        return;
    }
    if (configForm.deduct_on_sale === 'custom') {
        const any = configForm.ingredient_rules.some((r) => r.deduct_on_sale);
        if (!configForm.ingredient_rules.length || !any) {
            alert('اختر مقاديراً واحدة على الأقل تُخصم عند البيع.');
            return;
        }
    }
    const payload = {
        product_id: configForm.product_id,
        size: configForm.size || '',
        deduct_on_sale: configForm.deduct_on_sale,
        ingredient_rules: buildIngredientRulesPayload(),
    };
    if (editingConfig.value) {
        router.put(route('admin.fridge.configs.update', editingConfig.value.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                showConfigForm.value = false;
            },
        });
    } else {
        router.post(route('admin.fridge.configs.store'), payload, {
            preserveScroll: true,
            onSuccess: () => {
                showConfigForm.value = false;
            },
        });
    }
}

const showSaleIngredients = computed(() => configForm.deduct_on_sale === 'custom');

function deleteConfig(id) {
    if (!confirm('حذف هذا المنتج من إعدادات التلاجة؟')) return;
    router.delete(route('admin.fridge.configs.destroy', id), { preserveScroll: true });
}

function openPrint(cfg) {
    printModal.value = {
        open: true,
        configId: cfg.id,
        productName: cfg.product_name + (cfg.size ? ` (${translateSize(cfg.size)})` : ''),
        unit_count: 1,
        label_code: '',
        created: false,
    };
}

function submitPrint() {
    const n = parseFloat(printModal.value.unit_count);
    if (!printModal.value.configId || !n || n < 0.001) {
        alert('أدخل عدداً صالحاً.');
        return;
    }
    window.axios
        .post(route('admin.fridge.labels.store', printModal.value.configId), { unit_count: n })
        .then((res) => {
            printModal.value.created = true;
            printModal.value.label_code = res.data?.label_code || '';
            printModal.value.labelId = res.data?.id || null;
            const c = configs.value.find((x) => x.id === printModal.value.configId);
            if (c) {
                c.pending_units = (parseFloat(c.pending_units) || 0) + n;
            }
            nextTick(() => renderBarcode(barcodeSvg.value, printModal.value.label_code));
        })
        .catch((err) => {
            alert(err?.response?.data?.message || 'حدث خطأ.');
        });
}

function renderBarcode(el, code) {
    if (!el || !code) return;
    try {
        while (el.firstChild) el.removeChild(el.firstChild);
        JsBarcode(el, code, { format: 'CODE128', width: 2, height: 72, displayValue: false, margin: 8 });
    } catch (e) {
        console.error(e);
    }
}

function saleModeLabel(mode) {
    return deductModes.find((m) => m.value === mode)?.label || mode;
}

function formatIngredientQty(qty) {
    const n = parseFloat(qty);
    if (Number.isNaN(n)) return '—';
    return n % 1 === 0 ? String(n) : n.toFixed(2);
}

function stockForConfig(cfg) {
    const row = (props.fridge?.stocks || []).find(
        (s) => s.product_id === cfg.product_id && (s.size || '') === (cfg.size || '')
    );
    return row ? row.quantity : 0;
}

function goToBranchScope(branchId) {
    router.get(route('admin.raw-materials.index'), {
        tab: 'fridge',
        view_scope: String(branchId),
    }, { preserveState: false });
}
</script>

<template>
    <div class="space-y-6">
        <div v-if="page.props.flash?.success" class="bg-green-100 border border-green-300 text-green-900 px-4 py-3 rounded-lg text-sm no-print">
            {{ page.props.flash.success }}
        </div>

        <div class="flex flex-wrap justify-between items-center gap-3 no-print">
            <p class="text-gray-600 text-sm max-w-2xl">
                التكويد يُنشئ ملصقاً؛ عند مسحه في الفرع تُضاف الوحدات للتلاجة فقط (بدون خصم مقادير). عند البيع من الكاشير يُخصم من التلاجة وتُطبَّق قواعد المقادير.
                <span v-if="isCentralView" class="block mt-1 text-cyan-800 font-medium">
                    لمخزون فرع واحد بالتفصيل: اختر اسم الفرع من قائمة «عرض المخزون» أعلى الصفحة.
                </span>
            </p>
            <button v-if="canManage && isCentralView" type="button" class="btn-primary" @click="openNewConfig">
                ➕ إضافة منتج للتلاجة
            </button>
        </div>

        <!-- مركزي: ملخص كل الفروع -->
        <div v-if="isCentralView" class="bg-white border border-cyan-200 rounded-xl shadow-sm overflow-hidden">
            <div class="bg-cyan-700 text-white px-4 py-3 font-bold">مخزون التلاجة — كل الفروع</div>
            <div v-if="!fridge.stocksByBranch?.length" class="p-6 text-center text-gray-500 text-sm">لا توجد فروع.</div>
            <div v-else class="divide-y divide-gray-100">
                <div v-for="branch in fridge.stocksByBranch" :key="branch.branch_id" class="p-4">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                        <h4 class="font-bold text-gray-800">📍 {{ branch.branch_name }}</h4>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="text-cyan-800 font-semibold">الإجمالي: {{ branch.total_units }} وحدة</span>
                            <button
                                type="button"
                                class="text-cyan-700 hover:text-cyan-900 underline text-xs"
                                @click="goToBranchScope(branch.branch_id)"
                            >
                                عرض تفصيلي
                            </button>
                        </div>
                    </div>
                    <p v-if="!branch.items.length" class="text-sm text-gray-500">لا يوجد مخزون حالياً في تلاجة هذا الفرع.</p>
                    <table v-else class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-right border-b">المنتج</th>
                                <th class="p-2 text-right border-b">المقاس</th>
                                <th class="p-2 text-center border-b">الكمية</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in branch.items" :key="item.product_id + '-' + item.size" class="border-b last:border-0">
                                <td class="p-2">{{ item.product_name }}</td>
                                <td class="p-2 text-gray-600">{{ translateSize(item.size) }}</td>
                                <td class="p-2 text-center font-bold text-cyan-800">{{ item.quantity }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-else-if="fridge.stocks?.length" class="bg-cyan-50 border border-cyan-200 rounded-xl p-4">
            <h3 class="font-bold text-cyan-900 mb-3">مخزون التلاجة في هذا الفرع</h3>
            <ul class="space-y-2 text-sm">
                <li v-for="s in fridge.stocks" :key="s.product_id + '-' + s.size" class="flex justify-between">
                    <span>{{ s.product_name }} <span v-if="s.size" class="text-gray-500">({{ translateSize(s.size) }})</span></span>
                    <span class="font-bold">{{ s.quantity }} وحدة</span>
                </li>
            </ul>
        </div>
        <div v-else-if="!isCentralView" class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-sm text-gray-600 text-center">
            لا يوجد مخزون في تلاجة هذا الفرع حالياً.
        </div>

        <div
            v-if="canManage && isCentralView && configs.length"
            class="flex flex-wrap items-center gap-3 mb-3 no-print"
        >
            <button
                type="button"
                class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-2 px-4 rounded-lg disabled:opacity-40"
                :disabled="selectedRows.length < 2"
                @click="openCombinedModal"
            >
                🖨️ طباعة كود مجمّع
                <span v-if="selectedRows.length >= 2">({{ selectedRows.length }} منتجات)</span>
            </button>
            <span class="text-sm text-gray-600">حدّد منتجين أو أكثر من الجدول، ثم اضبط الكمية واطبع باركوداً واحداً.</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white shadow rounded-xl text-sm">
                <thead class="bg-cyan-700 text-white">
                    <tr>
                        <th v-if="canManage && isCentralView" class="p-3 text-center w-12">
                            <input
                                type="checkbox"
                                class="rounded"
                                :checked="allRowsSelected"
                                title="تحديد الكل"
                                @change="toggleSelectAll"
                            />
                        </th>
                        <th class="p-3 text-right">المنتج</th>
                        <th class="p-3 text-right">المقاس</th>
                        <th class="p-3 text-right">مقادير عند البيع</th>
                        <th class="p-3 text-right">مكوّد / بالتلاجة</th>
                        <th v-if="canManage && isCentralView" class="p-3 text-center">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="cfg in configs" :key="cfg.id" class="border-t hover:bg-gray-50" :class="{ 'bg-cyan-50': rowSelection[cfg.id]?.selected }">
                        <td v-if="canManage && isCentralView" class="p-3 text-center">
                            <input v-model="rowSelection[cfg.id].selected" type="checkbox" class="rounded" />
                        </td>
                        <td class="p-3 font-semibold">{{ cfg.product_name }}</td>
                        <td class="p-3">{{ translateSize(cfg.size) }}</td>
                        <td class="p-3">
                            <div class="font-medium">{{ saleModeLabel(cfg.deduct_on_sale) }}</div>
                            <ul
                                v-if="cfg.sale_ingredients?.length"
                                class="mt-1.5 space-y-0.5 text-xs text-gray-700"
                            >
                                <li
                                    v-for="ing in cfg.sale_ingredients"
                                    :key="ing.raw_material_id"
                                    class="flex flex-wrap items-baseline gap-1"
                                >
                                    <span class="font-semibold text-gray-800">{{ ing.name }}</span>
                                    <span class="text-gray-500">
                                        {{ formatIngredientQty(ing.quantity_consumed) }} {{ ing.consume_unit }}
                                        <span class="text-gray-400">/ وحدة</span>
                                    </span>
                                </li>
                            </ul>
                            <p
                                v-else-if="cfg.deduct_on_sale !== 'none'"
                                class="mt-1 text-xs text-amber-700"
                            >
                                لا توجد مقادير مربوطة بهذا المنتج/المقاس.
                            </p>
                        </td>
                        <td class="p-3">
                            <span v-if="cfg.pending_units > 0" class="text-amber-800 font-semibold block">
                                مكوّد: {{ cfg.pending_units }}
                            </span>
                            <span v-if="!isCentralView" class="text-cyan-800">بالتلاجة: {{ stockForConfig(cfg) }}</span>
                            <span v-if="isCentralView && cfg.pending_units <= 0" class="text-gray-400">—</span>
                        </td>
                        <td v-if="canManage && isCentralView" class="p-3 text-center">
                            <div v-if="rowSelection[cfg.id]?.selected" class="mb-2">
                                <label class="text-xs text-gray-600">كمية التكويد:</label>
                                <input
                                    v-model.number="rowSelection[cfg.id].unit_count"
                                    type="number"
                                    min="0.001"
                                    step="1"
                                    class="w-16 border rounded p-1 text-center text-xs mr-1"
                                />
                            </div>
                            <div class="space-x-1 space-x-reverse">
                            <button type="button" class="btn-green text-xs" @click="openPrint(cfg)">تكويد</button>
                            <button type="button" class="btn-yellow text-xs" @click="openEditConfig(cfg)">تعديل</button>
                            <button type="button" class="btn-red text-xs" @click="deleteConfig(cfg.id)">حذف</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!configs.length">
                        <td :colspan="canManage && isCentralView ? 6 : 5" class="p-8 text-center text-gray-500">لا توجد منتجات مُعرَّفة للتلاجة بعد.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showConfigForm" class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4" @click.self="showConfigForm = false">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 max-h-[90vh] overflow-y-auto" dir="rtl">
                <h3 class="text-lg font-bold mb-4">{{ editingConfig ? 'تعديل منتج التلاجة' : 'منتج جديد للتلاجة' }}</h3>
                <form class="space-y-4" @submit.prevent="submitConfig">
                    <div v-if="!editingConfig">
                        <label class="block font-medium mb-1">الفئة</label>
                        <SearchableSelect
                            v-model="selectedCategoryId"
                            :options="categorySelectOptions"
                            placeholder="ابحث عن فئة..."
                            empty-text="لا توجد فئة"
                            required
                        />
                    </div>
                    <div v-if="!editingConfig">
                        <label class="block font-medium mb-1">المنتج</label>
                        <SearchableSelect
                            v-model="configForm.product_id"
                            :options="productSelectOptions"
                            :placeholder="selectedCategoryId ? 'ابحث عن منتج...' : 'اختر الفئة أولاً'"
                            empty-text="لا يوجد منتج في هذه الفئة"
                            :required="!!selectedCategoryId"
                        />
                        <p v-if="selectedCategoryId && !productSelectOptions.length" class="text-xs text-amber-700 mt-1">
                            لا توجد منتجات منشورة في هذه الفئة.
                        </p>
                    </div>
                    <div v-if="!editingConfig && configForm.product_id && sizeSelectOptions.length">
                        <label class="block font-medium mb-1">المقاس</label>
                        <p class="text-xs text-gray-500 mb-2">اختر أحد المقاسات المتاحة لهذا المنتج:</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="opt in sizeSelectOptions"
                                :key="opt.value"
                                type="button"
                                class="px-4 py-2 rounded-lg font-semibold text-sm border-2 transition"
                                :class="
                                    configForm.size === opt.value
                                        ? 'bg-cyan-600 text-white border-cyan-700'
                                        : 'bg-white text-gray-800 border-gray-300 hover:border-cyan-400'
                                "
                                @click="configForm.size = opt.value"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                    </div>
                    <p
                        v-else-if="!editingConfig && configForm.product_id && !sizeSelectOptions.length"
                        class="text-sm text-gray-500"
                    >
                        هذا المنتج بدون مقاسات — يُضاف كوحدة واحدة.
                    </p>
                    <div>
                        <label class="block font-medium mb-1">عند البيع من الكاشير (التلاجة)</label>
                        <p class="text-xs text-gray-500 mb-2">
                            يُخصم عدد الوحدات من التلاجة دائماً. هنا تحدد إن كان يُخصم أيضاً من مقادير الفرع (مثل الكوب).
                        </p>
                        <select v-model="configForm.deduct_on_sale" class="w-full border rounded-lg p-2">
                            <option v-for="m in deductModes" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                    </div>
                    <div
                        v-if="showSaleIngredients"
                        class="border border-amber-200 bg-amber-50 rounded-lg p-3 space-y-2 max-h-52 overflow-y-auto"
                    >
                        <p v-if="!configForm.product_id" class="text-sm text-amber-800">اختر المنتج والمقاس أولاً.</p>
                        <p v-else-if="!configForm.ingredient_rules.length" class="text-sm text-gray-600">لا توجد مقادير مربوطة بهذا المنتج/المقاس.</p>
                        <template v-else>
                            <p class="text-xs font-semibold text-amber-900 mb-1">المقادير التي تُخصم من مخزون الفرع عند البيع:</p>
                            <label
                                v-for="(ing, idx) in configForm.ingredient_rules"
                                :key="ing.raw_material_id"
                                class="flex items-center gap-2 text-sm py-1 border-b border-amber-100 last:border-0 cursor-pointer"
                            >
                                <input v-model="configForm.ingredient_rules[idx].deduct_on_sale" type="checkbox" class="rounded" />
                                <span class="font-medium">{{ ing.name }}</span>
                                <span class="text-gray-500 text-xs">({{ ing.quantity_consumed }} لكل وحدة)</span>
                            </label>
                        </template>
                    </div>
                    <div class="flex gap-2 justify-end">
                        <button type="button" class="btn-gray" @click="showConfigForm = false">إلغاء</button>
                        <button type="submit" class="btn-primary" :disabled="configForm.processing">حفظ</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- كود مجمّع من الجدول -->
        <div v-if="combinedModal.open" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" @click.self="combinedModal.open = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" dir="rtl">
                <h3 class="text-lg font-bold mb-3">كود مجمّع للتلاجة</h3>
                <template v-if="!combinedModal.created">
                    <ul class="text-sm mb-4 space-y-2 border rounded-lg p-3 bg-gray-50">
                        <li v-for="cfg in selectedRows" :key="cfg.id" class="flex justify-between gap-2">
                            <span>
                                {{ cfg.product_name }}
                                <span class="text-gray-500">({{ translateSize(cfg.size) }})</span>
                            </span>
                            <strong>{{ rowSelection[cfg.id]?.unit_count }} وحدة</strong>
                        </li>
                    </ul>
                    <button type="button" class="btn-primary w-full" @click="submitCombinedPrint">إنشاء الباركود المجمّع</button>
                </template>
                <template v-else>
                    <ul class="text-sm mb-3 space-y-1 text-right">
                        <li v-for="(row, i) in combinedModal.resultLines" :key="i">
                            {{ row.product_name }}
                            <span v-if="row.size" class="text-gray-500">({{ translateSize(row.size) }})</span>
                            — {{ row.unit_count }} وحدة
                        </li>
                    </ul>
                    <svg ref="combinedBarcodeSvg" class="mx-auto mb-2"></svg>
                    <p class="font-mono text-center text-sm break-all mb-3">{{ combinedModal.label_code }}</p>
                    <a
                        v-if="combinedModal.labelId"
                        :href="route('admin.fridge.labels.print', combinedModal.labelId)"
                        target="_blank"
                        class="btn-primary w-full block text-center"
                    >🖨️ صفحة طباعة</a>
                </template>
                <button type="button" class="btn-gray w-full mt-3" @click="combinedModal.open = false">إغلاق</button>
            </div>
        </div>

        <div v-if="printModal.open" class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4" @click.self="printModal.open = false">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" dir="rtl">
                <h3 class="font-bold mb-2">تكويد — {{ printModal.productName }}</h3>
                <template v-if="!printModal.created">
                    <input v-model.number="printModal.unit_count" type="number" min="0.001" step="1" class="w-full border rounded-lg p-2 mb-4" />
                    <button type="button" class="btn-primary w-full" @click="submitPrint">إنشاء باركود</button>
                </template>
                <template v-else>
                    <p class="text-amber-800 bg-amber-50 border border-amber-200 rounded p-2 text-sm mb-3">
                        تم التكويد — عند مسح الفرع تُضاف الوحدات للتلاجة فقط (بدون خصم مقادير).
                    </p>
                    <svg ref="barcodeSvg" class="mx-auto"></svg>
                    <p class="font-mono text-center text-sm mt-2 break-all">{{ printModal.label_code }}</p>
                    <a
                        v-if="printModal.labelId"
                        :href="route('admin.fridge.labels.print', printModal.labelId)"
                        target="_blank"
                        class="btn-primary w-full mt-4 block text-center"
                    >
                        🖨️ صفحة طباعة
                    </a>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.btn-primary { @apply bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg; }
.btn-gray { @apply bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg; }
.btn-green { @apply bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded-lg; }
.btn-yellow { @apply bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded-lg; }
.btn-red { @apply bg-red-600 hover:bg-red-700 text-white font-bold py-1 px-3 rounded-lg; }
</style>
