<script setup>
import { computed } from 'vue';
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

const form = useForm({
    purchased_on: props.purchase?.purchased_on || new Date().toISOString().slice(0, 10),
    invoice_number: props.purchase?.invoice_number || '',
    notes: props.purchase?.notes || '',
    invoice: null,
    lines: props.purchase?.lines?.length ? props.purchase.lines.map(lineFromPurchase) : [blankLine()],
});

const grandTotal = computed(() => form.lines.reduce((sum, line) => {
    const qty = Number(line.quantity || 0);
    const price = Number(line.unit_price || 0);
    return sum + (qty * price);
}, 0));

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function materialsFor(line) {
    const pool = !line.category_id
        ? props.materials
        : props.materials.filter((item) => String(item.category_id || '') === String(line.category_id));
    const query = String(line.material_query || '').trim().toLowerCase();
    if (!query) {
        return pool;
    }
    return pool.filter((item) => String(item.name || '').toLowerCase().includes(query));
}

function materialOf(line) {
    return props.materials.find((item) => String(item.id) === String(line.product_id));
}

function lineTotal(line) {
    return Number(line.quantity || 0) * Number(line.unit_price || 0);
}

function addLine() {
    form.lines = [...form.lines, blankLine()];
}

function removeLine(index) {
    if (form.lines.length === 1) {
        form.lines = [blankLine()];
        return;
    }
    form.lines = form.lines.filter((_, lineIndex) => lineIndex !== index);
}

function onCategoryChange(line) {
    const stillVisible = materialsFor(line).some((item) => String(item.id) === String(line.product_id));
    if (!stillVisible) {
        line.product_id = '';
        line.material_query = '';
    }
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

function pickMaterial(line, material) {
    line.product_id = String(material.id);
    line.material_query = material.name;
    line.material_open = false;
}

function onMaterialInput(line) {
    line.material_open = true;
    const exact = materialsFor(line).find((item) => item.name === line.material_query);
    line.product_id = exact ? String(exact.id) : '';
}

function closeMaterialList(line) {
    window.setTimeout(() => {
        line.material_open = false;
    }, 150);
}

function onFile(event) {
    form.invoice = event.target.files?.[0] || null;
}

function fieldError(key) {
    return form.errors[key] || '';
}

function submit() {
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
            <div>
                <h2 class="font-semibold text-xl text-gray-800">{{ editing ? 'تعديل فاتورة' : 'فاتورة جديدة' }}</h2>
                <p class="text-sm text-gray-500">{{ supplier.name }}</p>
            </div>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <form class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6" @submit.prevent="submit">
                <section class="grid grid-cols-1 gap-4 rounded-2xl bg-white p-4 shadow-sm sm:grid-cols-2 sm:p-5">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">تاريخ الشراء *</label>
                        <input v-model="form.purchased_on" type="date" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        <p v-if="fieldError('purchased_on')" class="mt-1 text-sm text-red-600">{{ fieldError('purchased_on') }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">رقم الفاتورة</label>
                        <input v-model="form.invoice_number" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                </section>

                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-bold text-gray-900">بنود الفاتورة</h3>
                        <button type="button" class="rounded-lg px-4 py-2 text-sm font-bold text-white shadow" style="background-color: #16a34a; color: #ffffff;" @click="addLine">
                            إضافة بند
                        </button>
                    </div>
                    <p v-if="fieldError('lines')" class="text-sm text-red-600">{{ fieldError('lines') }}</p>

                    <article v-for="(line, index) in form.lines" :key="index" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex rounded-lg bg-gray-100 p-1 text-sm font-semibold">
                                <button type="button" class="rounded-md px-3 py-1.5" :class="line.line_type === 'raw' ? 'bg-white text-teal-800 shadow' : 'text-gray-600'" @click="line.line_type = 'raw'">
                                    مادة خام
                                </button>
                                <button type="button" class="rounded-md px-3 py-1.5" :class="line.line_type === 'custom' ? 'bg-white text-teal-800 shadow' : 'text-gray-600'" @click="line.line_type = 'custom'">
                                    بند مخصص
                                </button>
                            </div>
                            <button type="button" class="text-sm font-semibold text-red-600" @click="removeLine(index)">حذف البند</button>
                        </div>

                        <template v-if="line.line_type === 'raw'">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">فئة المادة</label>
                                    <select v-model="line.category_id" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" @change="onCategoryChange(line)">
                                        <option value="">كل الفئات</option>
                                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
                                    </select>
                                </div>
                                <div class="relative">
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">المادة الخام *</label>
                                    <input
                                        v-model="line.material_query"
                                        required
                                        autocomplete="off"
                                        placeholder="ابحث بالاسم"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5"
                                        @focus="line.material_open = true"
                                        @input="onMaterialInput(line)"
                                        @blur="closeMaterialList(line)"
                                    />
                                    <ul
                                        v-if="line.material_open"
                                        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                                    >
                                        <li v-if="!materialsFor(line).length" class="px-3 py-2 text-sm text-gray-500">لا توجد نتائج</li>
                                        <li
                                            v-for="material in materialsFor(line)"
                                            :key="material.id"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full flex-col items-start px-3 py-2 text-right hover:bg-gray-50"
                                                @mousedown.prevent="pickMaterial(line, material)"
                                            >
                                                <span class="font-semibold text-gray-900">{{ material.name }}</span>
                                                <span v-if="pieceLabel(material)" class="text-xs text-emerald-700">{{ pieceLabel(material) }}</span>
                                            </button>
                                        </li>
                                    </ul>
                                    <div v-if="materialOf(line) && pieceLabel(materialOf(line))" class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                                        <div class="font-semibold">{{ pieceLabel(materialOf(line)) }}</div>
                                        <div v-if="stockPreview(line)" class="text-xs">{{ stockPreview(line) }}</div>
                                    </div>
                                    <p v-if="fieldError(`lines.${index}.product_id`)" class="mt-1 text-sm text-red-600">{{ fieldError(`lines.${index}.product_id`) }}</p>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">أين تُضاف الكمية؟</label>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <label class="flex items-center gap-2 rounded-lg border px-3 py-2" :class="line.stock_destination === 'central' ? 'border-teal-400 bg-teal-50' : 'border-gray-200'">
                                        <input v-model="line.stock_destination" type="radio" value="central" />
                                        المخزون المركزي
                                    </label>
                                    <label class="flex items-center gap-2 rounded-lg border px-3 py-2" :class="line.stock_destination === 'branch' ? 'border-teal-400 bg-teal-50' : 'border-gray-200'">
                                        <input v-model="line.stock_destination" type="radio" value="branch" />
                                        مخزون فرع
                                    </label>
                                </div>
                                <select
                                    v-if="line.stock_destination === 'branch'"
                                    v-model="line.branch_id"
                                    required
                                    class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2.5"
                                >
                                    <option value="">اختر الفرع</option>
                                    <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                                </select>
                                <p v-if="fieldError(`lines.${index}.branch_id`)" class="mt-1 text-sm text-red-600">{{ fieldError(`lines.${index}.branch_id`) }}</p>
                            </div>
                        </template>

                        <template v-else>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">اسم البند *</label>
                                    <input v-model="line.custom_name" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="مثال: أكياس تغليف" />
                                    <p v-if="fieldError(`lines.${index}.custom_name`)" class="mt-1 text-sm text-red-600">{{ fieldError(`lines.${index}.custom_name`) }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">الوحدة</label>
                                    <input v-model="line.custom_unit" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" placeholder="قطعة / كرتونة" />
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">البند المخصص يُسجل في الفاتورة فقط ولا يغيّر المخزون.</p>
                        </template>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">الكمية *</label>
                                <input v-model="line.quantity" type="number" min="0.001" step="0.001" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">سعر الوحدة *</label>
                                <input v-model="line.unit_price" type="number" min="0" step="0.01" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                            </div>
                            <div class="rounded-lg bg-gray-50 px-3 py-2">
                                <div class="text-xs text-gray-500">إجمالي البند</div>
                                <div class="text-lg font-bold text-gray-900">{{ money(lineTotal(line)) }}</div>
                            </div>
                        </div>
                    </article>

                    <button type="button" class="w-full rounded-xl border border-dashed border-green-600 px-4 py-3 text-sm font-bold" style="background-color: #f0fdf4; color: #166534;" @click="addLine">
                        + إضافة بند آخر
                    </button>
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

                <div class="sticky bottom-3 flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-lg sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-sm text-gray-500">إجمالي الفاتورة</div>
                        <div class="text-2xl font-bold text-teal-800">{{ money(grandTotal) }}</div>
                    </div>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <Link :href="route('admin.suppliers.show', supplier.id)" class="rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm font-semibold text-gray-700">
                            إلغاء
                        </Link>
                        <button type="submit" class="rounded-lg bg-green-600 px-5 py-2.5 text-sm font-bold text-white shadow disabled:opacity-50" style="background-color: #16a34a; color: #ffffff;" :disabled="form.processing">
                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ الفاتورة' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
