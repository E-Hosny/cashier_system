<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    purchase: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    materials: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const editing = computed(() => !!props.purchase?.id);
const showLineModal = ref(false);
const editingLineIndex = ref(null);
const modalErrors = ref({});
const lineToast = ref('');
let lineToastTimer = null;

function blankLine() {
    return {
        line_type: 'raw',
        category_id: '',
        product_id: '',
        custom_name: '',
        custom_unit: '',
        quantity: '',
        unit_price: '',
        stock_destination: 'central',
        branch_id: '',
        material_query: '',
        material_open: false,
    };
}

function lineFromPurchase(line) {
    const material = props.materials.find((item) => item.id === line.product_id);
    return {
        line_type: line.line_type,
        category_id: material?.category_id ? String(material.category_id) : '',
        product_id: line.product_id ? String(line.product_id) : '',
        custom_name: line.custom_name || '',
        custom_unit: line.custom_unit || '',
        quantity: line.quantity,
        unit_price: line.unit_price,
        stock_destination: line.stock_destination === 'branch' ? 'branch' : 'central',
        branch_id: line.branch_id ? String(line.branch_id) : '',
        material_query: material?.name || line.item_name || '',
        material_open: false,
    };
}

function cloneLine(line) {
    return {
        ...line,
        material_open: false,
    };
}

const form = useForm({
    purchased_on: props.purchase?.purchased_on || new Date().toISOString().slice(0, 10),
    invoice_number: props.purchase?.invoice_number || '',
    notes: props.purchase?.notes || '',
    invoice: null,
    lines: props.purchase?.lines?.length ? props.purchase.lines.map(lineFromPurchase) : [],
});

const draft = ref(blankLine());

const grandTotal = computed(() => form.lines.reduce((sum, line) => sum + lineTotal(line), 0));
const draftTotal = computed(() => lineTotal(draft.value));
const isEditingLine = computed(() => editingLineIndex.value !== null);

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function materialsFor(line) {
    const pool = !line.category_id
        ? props.materials
        : props.materials.filter((item) => String(item.category_id || '') === String(line.category_id));
    const query = String(line.material_query || '').trim().toLowerCase();
    if (!query) {
        return pool.slice(0, 40);
    }
    return pool.filter((item) => String(item.name || '').toLowerCase().includes(query)).slice(0, 40);
}

function materialOf(line) {
    return props.materials.find((item) => String(item.id) === String(line.product_id));
}

function branchOf(line) {
    return props.branches.find((item) => String(item.id) === String(line.branch_id));
}

function lineTotal(line) {
    return Number(line.quantity || 0) * Number(line.unit_price || 0);
}

function lineName(line) {
    if (line.line_type === 'custom') {
        return line.custom_name || 'بند مخصص';
    }
    return materialOf(line)?.name || line.material_query || 'مادة خام';
}

function lineUnit(line) {
    if (line.line_type === 'custom') {
        return line.custom_unit || '—';
    }
    return materialOf(line)?.unit || 'قطعة';
}

function lineMeta(line) {
    if (line.line_type === 'custom') {
        return 'بند مخصص · بدون مخزون';
    }
    if (line.stock_destination === 'branch') {
        return `مخزون فرع · ${branchOf(line)?.name || 'غير محدد'}`;
    }
    return 'المخزون المركزي';
}

function pieceLabel(material) {
    if (!material?.quantity_per_unit) {
        return '';
    }
    const consume = material.consume_unit || 'وحدة';
    return `عدد وحدات القطعة: ${Number(material.quantity_per_unit)} ${consume}`;
}

function stockPreview(line) {
    const material = materialOf(line);
    const perPiece = Number(material?.quantity_per_unit || 0);
    const qty = Number(line.quantity || 0);
    if (!material || !perPiece || !qty) {
        return '';
    }
    const total = Math.round(qty * perPiece * 1000) / 1000;
    return `تُضاف للمخزون: ${qty} × ${perPiece} = ${total} ${material.consume_unit || 'وحدة'}`;
}

function openAddLineModal() {
    editingLineIndex.value = null;
    draft.value = blankLine();
    modalErrors.value = {};
    lineToast.value = '';
    showLineModal.value = true;
}

function openEditLineModal(index) {
    editingLineIndex.value = index;
    draft.value = cloneLine(form.lines[index]);
    modalErrors.value = {};
    lineToast.value = '';
    showLineModal.value = true;
}

function closeLineModal() {
    showLineModal.value = false;
    editingLineIndex.value = null;
    draft.value = blankLine();
    modalErrors.value = {};
    lineToast.value = '';
    if (lineToastTimer) {
        window.clearTimeout(lineToastTimer);
        lineToastTimer = null;
    }
}

function validateDraft() {
    const errors = {};
    const line = draft.value;

    if (line.line_type === 'raw') {
        if (!line.product_id) {
            errors.product_id = 'اختر المادة الخام.';
        }
        if (line.stock_destination === 'branch' && !line.branch_id) {
            errors.branch_id = 'اختر الفرع.';
        }
    } else if (!String(line.custom_name || '').trim()) {
        errors.custom_name = 'اسم البند مطلوب.';
    }

    if (!(Number(line.quantity) > 0)) {
        errors.quantity = 'أدخل كمية صحيحة.';
    }
    if (!(Number(line.unit_price) >= 0) || line.unit_price === '' || line.unit_price === null) {
        errors.unit_price = 'أدخل سعر الوحدة.';
    }

    modalErrors.value = errors;
    return Object.keys(errors).length === 0;
}

function showLineToast(message) {
    lineToast.value = message;
    if (lineToastTimer) {
        window.clearTimeout(lineToastTimer);
    }
    lineToastTimer = window.setTimeout(() => {
        lineToast.value = '';
        lineToastTimer = null;
    }, 2200);
}

async function saveDraftLine() {
    if (!validateDraft()) {
        return;
    }

    const next = cloneLine(draft.value);
    if (isEditingLine.value) {
        form.lines.splice(editingLineIndex.value, 1, next);
        showLineToast('تم تحديث البند');
        closeLineModal();
        return;
    }

    form.lines.push(next);
    showLineToast('تمت إضافة البند للفاتورة');
    editingLineIndex.value = null;
    draft.value = blankLine();
    modalErrors.value = {};
}

function removeLine(index) {
    form.lines.splice(index, 1);
}

function onCategoryChange() {
    const stillVisible = materialsFor(draft.value).some((item) => String(item.id) === String(draft.value.product_id));
    if (!stillVisible) {
        draft.value.product_id = '';
        draft.value.material_query = '';
    }
}

function pickMaterial(material) {
    draft.value.product_id = String(material.id);
    draft.value.material_query = material.name;
    draft.value.category_id = material.category_id ? String(material.category_id) : '';
    draft.value.material_open = false;
    modalErrors.value.product_id = '';
}

function onMaterialInput() {
    draft.value.material_open = true;
    const exact = materialsFor(draft.value).find((item) => item.name === draft.value.material_query);
    draft.value.product_id = exact ? String(exact.id) : '';
}

function closeMaterialList() {
    window.setTimeout(() => {
        draft.value.material_open = false;
    }, 150);
}

function onFile(event) {
    form.invoice = event.target.files?.[0] || null;
}

function fieldError(key) {
    return form.errors[key] || '';
}

function submit() {
    if (!form.lines.length) {
        form.setError('lines', 'أضف بنداً واحداً على الأقل.');
        return;
    }

    const options = { forceFormData: true };
    if (editing.value) {
        form.put(route('admin.suppliers.purchases.update', [props.supplier.id, props.purchase.id]), options);
        return;
    }
    form.post(route('admin.suppliers.purchases.store', props.supplier.id), options);
}
</script>

<template>
    <AppLayout :title="editing ? 'تعديل فاتورة' : 'فاتورة مورد'">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.suppliers.show', supplier.id)"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-bold text-gray-800"
                >
                    → رجوع
                </Link>
                <div class="min-w-0">
                    <h2 class="truncate font-semibold text-xl text-gray-800">{{ editing ? 'تعديل فاتورة' : 'فاتورة جديدة' }}</h2>
                    <p class="truncate text-sm text-gray-500">{{ supplier.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <form class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 pb-28" @submit.prevent="submit">
                <Link
                    :href="route('admin.suppliers.show', supplier.id)"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 shadow-sm sm:hidden"
                >
                    → رجوع إلى المورد
                </Link>

                <section class="rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 border-b border-dashed border-gray-200 pb-3">
                        <div class="text-xs font-semibold tracking-wide text-gray-400">بيانات الفاتورة</div>
                        <div class="mt-1 text-lg font-bold text-gray-900">{{ supplier.name }}</div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">تاريخ الشراء *</label>
                            <input v-model="form.purchased_on" type="date" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            <p v-if="fieldError('purchased_on')" class="mt-1 text-sm text-red-600">{{ fieldError('purchased_on') }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">رقم الفاتورة</label>
                            <input v-model="form.invoice_number" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="اختياري" />
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 sm:px-5">
                        <div>
                            <h3 class="font-bold text-gray-900">بنود الفاتورة</h3>
                            <p class="text-xs text-gray-500">{{ form.lines.length }} بند</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-bold text-white shadow"
                            style="background-color:#16a34a;color:#fff"
                            @click="openAddLineModal"
                        >
                            + إضافة بند
                        </button>
                    </div>

                    <p v-if="fieldError('lines')" class="px-4 pt-3 text-sm text-red-600 sm:px-5">{{ fieldError('lines') }}</p>

                    <div v-if="!form.lines.length" class="px-4 py-10 text-center sm:px-5">
                        <p class="text-gray-500">لا توجد بنود بعد.</p>
                        <button
                            type="button"
                            class="mt-3 rounded-lg px-4 py-2 text-sm font-bold text-white"
                            style="background-color:#16a34a;color:#fff"
                            @click="openAddLineModal"
                        >
                            إضافة أول بند
                        </button>
                    </div>

                    <div v-else>
                        <div class="hidden border-b border-gray-100 bg-gray-50 px-5 py-2 text-xs font-semibold text-gray-500 sm:grid sm:grid-cols-12 sm:gap-2">
                            <div class="col-span-5">البند</div>
                            <div class="col-span-2 text-center">الكمية</div>
                            <div class="col-span-2 text-center">السعر</div>
                            <div class="col-span-2 text-left">الإجمالي</div>
                            <div class="col-span-1" />
                        </div>

                        <article
                            v-for="(line, index) in form.lines"
                            :key="`${index}-${line.product_id || line.custom_name}`"
                            class="border-b border-gray-100 px-4 py-3 last:border-b-0 sm:px-5"
                        >
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-12 sm:items-center sm:gap-2">
                                <div class="sm:col-span-5">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900">{{ lineName(line) }}</div>
                                            <div class="text-xs text-gray-500">{{ lineMeta(line) }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-sm sm:col-span-2 sm:block sm:text-center">
                                    <span class="text-gray-500 sm:hidden">الكمية</span>
                                    <span class="font-semibold text-gray-800">{{ line.quantity }} {{ lineUnit(line) }}</span>
                                </div>
                                <div class="flex justify-between text-sm sm:col-span-2 sm:block sm:text-center">
                                    <span class="text-gray-500 sm:hidden">السعر</span>
                                    <span class="font-semibold text-gray-800">{{ money(line.unit_price) }}</span>
                                </div>
                                <div class="flex justify-between sm:col-span-2 sm:block sm:text-left">
                                    <span class="text-gray-500 sm:hidden">الإجمالي</span>
                                    <span class="text-base font-bold text-gray-900">{{ money(lineTotal(line)) }}</span>
                                </div>
                                <div class="flex gap-2 sm:col-span-1 sm:justify-end">
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700"
                                        @click="openEditLineModal(index)"
                                    >
                                        تعديل
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700"
                                        @click="removeLine(index)"
                                    >
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </article>

                        <div class="flex items-center justify-between bg-gray-50 px-4 py-3 sm:px-5">
                            <span class="text-sm font-semibold text-gray-600">الإجمالي</span>
                            <span class="text-xl font-bold text-gray-900">{{ money(grandTotal) }}</span>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ملف الفاتورة (اختياري)</label>
                        <input
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            class="block w-full text-sm text-gray-600"
                            @change="onFile"
                        />
                        <p class="mt-1 text-xs text-gray-500">PDF أو صورة، بحد أقصى 8 ميجابايت.</p>
                        <p v-if="editing && purchase.invoice_original_name" class="mt-1 text-xs text-gray-600">
                            الملف الحالي: {{ purchase.invoice_original_name }}. اترك الحقل فارغاً للإبقاء عليه.
                        </p>
                        <p v-if="fieldError('invoice')" class="mt-1 text-sm text-red-600">{{ fieldError('invoice') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ملاحظات</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                </section>
            </form>

            <div class="fixed bottom-0 inset-x-0 z-30 border-t border-gray-200 bg-white/95 backdrop-blur">
                <div class="mx-auto flex max-w-3xl flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <div class="text-sm text-gray-500">إجمالي الفاتورة</div>
                        <div class="text-2xl font-bold text-teal-800">{{ money(grandTotal) }}</div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <Link :href="route('admin.suppliers.show', supplier.id)" class="rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm font-bold text-gray-700">
                            → رجوع
                        </Link>
                        <button
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-bold text-white shadow disabled:opacity-50"
                            style="background-color:#16a34a;color:#fff"
                            :disabled="form.processing || !form.lines.length"
                            @click="submit"
                        >
                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ الفاتورة' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showLineModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
            @click.self="closeLineModal"
        >
            <div class="flex max-h-[92vh] w-full max-w-lg flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" dir="rtl" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ isEditingLine ? 'تعديل بند' : 'إضافة بند' }}</h3>
                        <p class="text-sm text-gray-500">املأ البند ثم احفظه في الفاتورة</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-100" @click="closeLineModal">
                        إغلاق
                    </button>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto p-5">
                    <div class="flex rounded-lg bg-gray-100 p-1 text-sm font-semibold">
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-2"
                            :class="draft.line_type === 'raw' ? 'bg-white text-teal-800 shadow' : 'text-gray-600'"
                            @click="draft.line_type = 'raw'"
                        >
                            مادة خام
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-md px-3 py-2"
                            :class="draft.line_type === 'custom' ? 'bg-white text-teal-800 shadow' : 'text-gray-600'"
                            @click="draft.line_type = 'custom'"
                        >
                            بند مخصص
                        </button>
                    </div>

                    <template v-if="draft.line_type === 'raw'">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">فئة المادة</label>
                            <select v-model="draft.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" @change="onCategoryChange">
                                <option value="">كل الفئات</option>
                                <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                            </select>
                        </div>
                        <div class="relative">
                            <label class="mb-1 block text-sm font-semibold text-gray-700">المادة الخام *</label>
                            <input
                                v-model="draft.material_query"
                                autocomplete="off"
                                placeholder="ابحث بالاسم"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5"
                                @focus="draft.material_open = true"
                                @input="onMaterialInput"
                                @blur="closeMaterialList"
                            />
                            <ul
                                v-if="draft.material_open"
                                class="absolute z-20 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                            >
                                <li v-if="!materialsFor(draft).length" class="px-3 py-2 text-sm text-gray-500">لا توجد نتائج</li>
                                <li v-for="material in materialsFor(draft)" :key="material.id">
                                    <button
                                        type="button"
                                        class="flex w-full flex-col items-start px-3 py-2 text-right hover:bg-gray-50"
                                        @mousedown.prevent="pickMaterial(material)"
                                    >
                                        <span class="font-semibold text-gray-900">{{ material.name }}</span>
                                        <span v-if="pieceLabel(material)" class="text-xs text-emerald-700">{{ pieceLabel(material) }}</span>
                                    </button>
                                </li>
                            </ul>
                            <div v-if="materialOf(draft) && pieceLabel(materialOf(draft))" class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                                <div class="font-semibold">{{ pieceLabel(materialOf(draft)) }}</div>
                                <div v-if="stockPreview(draft)" class="text-xs">{{ stockPreview(draft) }}</div>
                            </div>
                            <p v-if="modalErrors.product_id" class="mt-1 text-sm text-red-600">{{ modalErrors.product_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">أين تُضاف الكمية؟</label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2.5" :class="draft.stock_destination === 'central' ? 'border-teal-400 bg-teal-50' : 'border-gray-200'">
                                    <input v-model="draft.stock_destination" type="radio" value="central" />
                                    المخزون المركزي
                                </label>
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2.5" :class="draft.stock_destination === 'branch' ? 'border-teal-400 bg-teal-50' : 'border-gray-200'">
                                    <input v-model="draft.stock_destination" type="radio" value="branch" />
                                    مخزون فرع
                                </label>
                            </div>
                            <select
                                v-if="draft.stock_destination === 'branch'"
                                v-model="draft.branch_id"
                                class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5"
                            >
                                <option value="">اختر الفرع</option>
                                <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                            </select>
                            <p v-if="modalErrors.branch_id" class="mt-1 text-sm text-red-600">{{ modalErrors.branch_id }}</p>
                        </div>
                    </template>

                    <template v-else>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">اسم البند *</label>
                            <input v-model="draft.custom_name" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="مثال: أكياس تغليف" />
                            <p v-if="modalErrors.custom_name" class="mt-1 text-sm text-red-600">{{ modalErrors.custom_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">الوحدة</label>
                            <input v-model="draft.custom_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="قطعة / كرتونة" />
                        </div>
                        <p class="text-xs text-gray-500">البند المخصص يُسجل في الفاتورة فقط ولا يغيّر المخزون.</p>
                    </template>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">الكمية *</label>
                            <input v-model="draft.quantity" type="number" min="0.001" step="0.001" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            <p v-if="modalErrors.quantity" class="mt-1 text-sm text-red-600">{{ modalErrors.quantity }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">سعر الوحدة *</label>
                            <input v-model="draft.unit_price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            <p v-if="modalErrors.unit_price" class="mt-1 text-sm text-red-600">{{ modalErrors.unit_price }}</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-gray-50 px-4 py-3">
                        <div class="text-xs text-gray-500">إجمالي البند</div>
                        <div class="text-xl font-bold text-gray-900">{{ money(draftTotal) }}</div>
                    </div>
                </div>

                <div class="border-t border-gray-100 p-4">
                    <div
                        v-if="lineToast"
                        class="mb-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-semibold text-green-800"
                    >
                        {{ lineToast }}
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="flex-1 rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700" @click="closeLineModal">
                            {{ form.lines.length ? 'تم' : 'إلغاء' }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg px-4 py-2.5 text-sm font-bold text-white"
                            style="background-color:#16a34a;color:#fff"
                            @click="saveDraftLine"
                        >
                            {{ isEditingLine ? 'تحديث البند' : 'إضافة للفاتورة' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
