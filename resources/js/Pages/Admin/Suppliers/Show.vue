<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    purchases: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    balance: { type: Object, default: () => ({ owed: 0, paid: 0, remaining: 0 }) },
    canViewPayments: { type: Boolean, default: false },
    canManagePayments: { type: Boolean, default: false },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const showPaymentModal = ref(false);
const selectedPurchase = ref(null);
const invoiceFilePurchase = ref(null);

const invoiceFileUrl = computed(() => {
    if (!invoiceFilePurchase.value) {
        return '';
    }
    return route('admin.suppliers.purchases.invoice', [props.supplier.id, invoiceFilePurchase.value.id]);
});

const invoiceFileIsImage = computed(() => {
    const mime = String(invoiceFilePurchase.value?.invoice_mime || '').toLowerCase();
    return mime.startsWith('image/');
});

const invoiceFileIsPdf = computed(() => {
    const mime = String(invoiceFilePurchase.value?.invoice_mime || '').toLowerCase();
    const name = String(invoiceFilePurchase.value?.invoice_original_name || '').toLowerCase();
    return mime.includes('pdf') || name.endsWith('.pdf');
});

const canEdit = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return props.canManage
        || permissions.includes('suppliers.manage')
        || roles.includes('super admin');
});
const showPayments = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return props.canViewPayments
        || permissions.includes('suppliers.payments.view')
        || permissions.includes('suppliers.payments.manage')
        || roles.includes('super admin');
});
const canPay = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return props.canManagePayments
        || permissions.includes('suppliers.payments.manage')
        || roles.includes('super admin');
});

const available = computed(() => Number(props.balance.remaining || 0));

const paymentForm = useForm({
    amount: '',
    paid_on: new Date().toISOString().slice(0, 10),
    method: 'cash',
    notes: '',
});

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function destinationLabel(line) {
    if (line.line_type !== 'raw') {
        return 'بدون أثر على المخزون';
    }
    if (line.stock_destination === 'branch') {
        return `مخزون فرع ${line.branch_name || ''}`.trim();
    }
    return 'المخزون المركزي';
}

function openPaymentModal() {
    paymentForm.clearErrors();
    paymentForm.amount = available.value > 0 ? available.value : '';
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.method = 'cash';
    paymentForm.notes = '';
    showPaymentModal.value = true;
}

function closePaymentModal() {
    if (paymentForm.processing) {
        return;
    }
    showPaymentModal.value = false;
}

function submitPayment() {
    paymentForm.post(route('admin.suppliers.payments.store', props.supplier.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset('amount', 'notes');
        },
    });
}

function openPurchaseModal(purchase) {
    selectedPurchase.value = purchase;
}

function closePurchaseModal() {
    selectedPurchase.value = null;
}

function openInvoiceFile(purchase, event) {
    event?.stopPropagation?.();
    if (!purchase?.has_invoice) {
        return;
    }
    invoiceFilePurchase.value = purchase;
}

function closeInvoiceFile() {
    invoiceFilePurchase.value = null;
}

function removeSupplier() {
    if (!confirm(`حذف المورد ${props.supplier.name}؟`)) {
        return;
    }
    router.delete(route('admin.suppliers.destroy', props.supplier.id));
}

function removePurchase(purchase) {
    if (!confirm('حذف هذه الفاتورة سيعكس الكمية من المخزون. هل تريد المتابعة؟')) {
        return;
    }
    selectedPurchase.value = null;
    invoiceFilePurchase.value = null;
    router.delete(route('admin.suppliers.purchases.destroy', [props.supplier.id, purchase.id]), {
        preserveScroll: true,
    });
}

watch(available, (value) => {
    if (showPaymentModal.value && !paymentForm.isDirty) {
        paymentForm.amount = value > 0 ? value : '';
    }
});
</script>

<template>
    <AppLayout :title="supplier.name">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.suppliers.index')"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-bold text-gray-800"
                >
                    → رجوع
                </Link>
                <h2 class="truncate font-semibold text-xl text-gray-800">{{ supplier.name }}</h2>
            </div>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <div class="mx-auto max-w-5xl space-y-5 px-4 sm:px-6">
                <Link
                    :href="route('admin.suppliers.index')"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 shadow-sm sm:hidden"
                >
                    → رجوع إلى الموردين
                </Link>

                <div v-if="flash.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

                <!-- رأس المورد -->
                <section class="rounded-2xl bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-bold text-gray-900">{{ supplier.name }}</h1>
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="supplier.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ supplier.is_active ? 'نشط' : 'متوقف' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                {{ supplier.contact_person || 'بدون مسؤول' }}
                                <span v-if="supplier.phone"> · {{ supplier.phone }}</span>
                                <span v-if="supplier.email"> · {{ supplier.email }}</span>
                            </p>
                            <p v-if="supplier.notes" class="text-sm text-gray-500">{{ supplier.notes }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="canPay"
                                type="button"
                                class="rounded-lg px-4 py-2 text-sm font-bold text-white"
                                style="background-color:#16a34a;color:#fff"
                                @click="openPaymentModal"
                            >
                                إضافة دفعة
                            </button>
                            <Link
                                v-if="showPayments"
                                :href="route('admin.suppliers.payments.index', supplier.id)"
                                class="rounded-lg bg-amber-100 px-4 py-2 text-sm font-bold text-amber-950"
                            >
                                الدفعات
                            </Link>
                            <Link
                                v-if="canEdit"
                                :href="route('admin.suppliers.purchases.create', supplier.id)"
                                class="rounded-lg px-4 py-2 text-sm font-bold text-white"
                                style="background-color:#1d4ed8;color:#fff"
                            >
                                فاتورة جديدة
                            </Link>
                            <Link
                                v-if="canEdit"
                                :href="route('admin.suppliers.edit', supplier.id)"
                                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800"
                            >
                                تعديل
                            </Link>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700"
                                @click="removeSupplier"
                            >
                                حذف
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ملخص الحساب -->
                <section v-if="showPayments" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <div class="text-sm text-gray-500">إجمالي الفواتير</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ money(balance.owed) }}</div>
                    </div>
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <div class="text-sm text-gray-500">المدفوع</div>
                        <div class="mt-1 text-2xl font-bold text-green-700">{{ money(balance.paid) }}</div>
                    </div>
                    <div
                        class="rounded-2xl p-4 shadow-sm"
                        :style="Number(balance.remaining) > 0 ? 'background-color:#fef3c7' : 'background-color:#dcfce7'"
                    >
                        <div class="text-sm font-semibold">المتبقي</div>
                        <div class="mt-1 text-2xl font-bold">{{ money(balance.remaining) }}</div>
                    </div>
                </section>

                <!-- الفواتير -->
                <section class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-lg font-bold text-gray-900">الفواتير</h3>
                        <Link
                            v-if="canEdit"
                            :href="route('admin.suppliers.purchases.create', supplier.id)"
                            class="rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-bold text-blue-800"
                        >
                            فاتورة جديدة
                        </Link>
                    </div>

                    <div v-if="!purchases.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                        لا توجد فواتير لهذا المورد بعد.
                    </div>

                    <button
                        v-for="purchase in purchases"
                        :key="purchase.id"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border border-gray-100 bg-white p-4 text-right shadow-sm transition hover:border-teal-200 hover:bg-teal-50/30"
                        @click="openPurchaseModal(purchase)"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-gray-900">{{ purchase.purchased_on }}</div>
                            <div class="text-sm text-gray-500">{{ purchase.lines.length }} بند</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-lg font-bold text-teal-800">{{ money(purchase.total_amount) }}</span>
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                عرض
                            </span>
                            <span
                                v-if="purchase.has_invoice"
                                role="button"
                                tabindex="0"
                                title="عرض ملف الفاتورة"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                                @click="openInvoiceFile(purchase, $event)"
                                @keydown.enter.prevent="openInvoiceFile(purchase, $event)"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm1 7V3.5L19.5 9H15zM8 13h8v2H8v-2zm0 4h8v2H8v-2zm0-8h5v2H8V9z" />
                                </svg>
                            </span>
                        </div>
                    </button>
                </section>
            </div>
        </div>

        <!-- مودال عرض الفاتورة -->
        <div
            v-if="selectedPurchase"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center sm:p-4"
            @click.self="closePurchaseModal"
        >
            <div class="flex max-h-[94vh] w-full max-w-2xl flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" dir="rtl" @click.stop>
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div>
                        <div class="text-xs font-semibold tracking-wide text-gray-400">فاتورة شراء</div>
                        <h3 class="text-lg font-bold text-gray-900">{{ supplier.name }}</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ selectedPurchase.purchased_on }}
                            ·
                            {{ selectedPurchase.invoice_number ? `رقم ${selectedPurchase.invoice_number}` : 'بدون رقم' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-100" @click="closePurchaseModal">
                        إغلاق
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div class="border-b border-dashed border-gray-200 px-5 py-4">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs text-gray-400">المورد</div>
                                <div class="font-semibold text-gray-900">{{ supplier.name }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">التاريخ</div>
                                <div class="font-semibold text-gray-900">{{ selectedPurchase.purchased_on }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">رقم الفاتورة</div>
                                <div class="font-semibold text-gray-900">{{ selectedPurchase.invoice_number || '—' }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-400">عدد البنود</div>
                                <div class="font-semibold text-gray-900">{{ selectedPurchase.lines.length }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="hidden border-b border-gray-100 bg-gray-50 px-5 py-2 text-xs font-semibold text-gray-500 sm:grid sm:grid-cols-12 sm:gap-2">
                        <div class="col-span-5">البند</div>
                        <div class="col-span-2 text-center">الكمية</div>
                        <div class="col-span-2 text-center">السعر</div>
                        <div class="col-span-3 text-left">الإجمالي</div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="(line, index) in selectedPurchase.lines"
                            :key="line.id"
                            class="px-5 py-3"
                        >
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 sm:items-start sm:gap-2">
                                <div class="sm:col-span-5">
                                    <div class="flex items-start gap-2">
                                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-bold text-gray-900">{{ line.item_name }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ line.line_type === 'raw' ? 'مادة خام' : 'بند مخصص' }}
                                                · {{ destinationLabel(line) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between text-sm sm:col-span-2 sm:block sm:text-center">
                                    <span class="text-gray-500 sm:hidden">الكمية</span>
                                    <span class="font-semibold text-gray-800">{{ line.quantity }} {{ line.unit || '' }}</span>
                                </div>
                                <div class="flex justify-between text-sm sm:col-span-2 sm:block sm:text-center">
                                    <span class="text-gray-500 sm:hidden">السعر</span>
                                    <span class="font-semibold text-gray-800">{{ money(line.unit_price) }}</span>
                                </div>
                                <div class="flex justify-between sm:col-span-3 sm:block sm:text-left">
                                    <span class="text-gray-500 sm:hidden">الإجمالي</span>
                                    <span class="font-bold text-gray-900">{{ money(line.line_total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="selectedPurchase.notes" class="border-t border-gray-100 px-5 py-3 text-sm text-gray-600">
                        <span class="font-semibold text-gray-700">ملاحظات:</span> {{ selectedPurchase.notes }}
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-5 py-4">
                        <span class="text-sm font-semibold text-gray-600">إجمالي الفاتورة</span>
                        <span class="text-2xl font-bold text-teal-800">{{ money(selectedPurchase.total_amount) }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-t border-gray-100 p-4">
                    <button
                        v-if="selectedPurchase.has_invoice"
                        type="button"
                        class="rounded-lg px-4 py-2.5 text-sm font-bold text-white"
                        style="background-color:#16a34a;color:#fff"
                        @click="openInvoiceFile(selectedPurchase)"
                    >
                        فتح الملف
                    </button>
                    <Link
                        v-if="canEdit"
                        :href="route('admin.suppliers.purchases.edit', [supplier.id, selectedPurchase.id])"
                        class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-800"
                    >
                        تعديل
                    </Link>
                    <button
                        v-if="canEdit"
                        type="button"
                        class="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700"
                        @click="removePurchase(selectedPurchase)"
                    >
                        حذف
                    </button>
                    <button
                        type="button"
                        class="mr-auto rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700"
                        @click="closePurchaseModal"
                    >
                        إغلاق
                    </button>
                </div>
            </div>
        </div>

        <!-- مودال ملف الفاتورة -->
        <div
            v-if="invoiceFilePurchase"
            class="fixed inset-0 z-[60] flex items-end justify-center bg-black/50 sm:items-center sm:p-4"
            @click.self="closeInvoiceFile"
        >
            <div class="flex max-h-[94vh] w-full max-w-3xl flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" dir="rtl" @click.stop>
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-900">ملف الفاتورة</h3>
                        <p class="truncate text-sm text-gray-500">
                            {{ invoiceFilePurchase.invoice_original_name || invoiceFilePurchase.purchased_on }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-100" @click="closeInvoiceFile">
                        إغلاق
                    </button>
                </div>

                <div class="flex-1 overflow-auto bg-gray-50 p-3 sm:p-4">
                    <img
                        v-if="invoiceFileIsImage"
                        :src="invoiceFileUrl"
                        :alt="invoiceFilePurchase.invoice_original_name || 'فاتورة'"
                        class="mx-auto max-h-[70vh] w-auto max-w-full rounded-lg object-contain shadow"
                    />
                    <iframe
                        v-else-if="invoiceFileIsPdf"
                        :src="invoiceFileUrl"
                        class="h-[70vh] w-full rounded-lg border border-gray-200 bg-white"
                        title="ملف الفاتورة"
                    />
                    <div v-else class="rounded-xl bg-white p-6 text-center shadow-sm">
                        <p class="text-sm text-gray-600">تعذر المعاينة داخل الصفحة لهذا النوع من الملفات.</p>
                        <a
                            :href="invoiceFileUrl"
                            target="_blank"
                            class="mt-3 inline-flex rounded-lg px-4 py-2 text-sm font-bold text-white"
                            style="background-color:#16a34a;color:#fff"
                        >
                            فتح الملف
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- مودال إضافة دفعة -->
        <div
            v-if="showPaymentModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4"
            @click.self="closePaymentModal"
        >
            <div class="w-full max-w-lg rounded-t-2xl bg-white shadow-xl sm:rounded-2xl" dir="rtl" @click.stop>
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">إضافة دفعة</h3>
                        <p class="text-sm text-gray-500">{{ supplier.name }}</p>
                    </div>
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-100" @click="closePaymentModal">
                        إغلاق
                    </button>
                </div>

                <form class="space-y-4 p-5" @submit.prevent="submitPayment">
                    <div class="rounded-xl px-4 py-3" style="background-color:#fef3c7">
                        <div class="text-sm font-semibold text-gray-700">المتبقي حالياً</div>
                        <div class="text-2xl font-bold text-gray-900">{{ money(available) }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">المبلغ *</label>
                        <input
                            v-model="paymentForm.amount"
                            type="number"
                            min="0.01"
                            :max="available"
                            step="0.01"
                            required
                            class="w-full rounded-lg border border-gray-300 px-3 py-2.5"
                        />
                        <p v-if="paymentForm.errors.amount" class="mt-1 text-sm text-red-600">{{ paymentForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">تاريخ الدفعة *</label>
                        <input v-model="paymentForm.paid_on" type="date" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">طريقة الدفع</label>
                        <select v-model="paymentForm.method" class="w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="cash">نقدي</option>
                            <option value="transfer">تحويل</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ملاحظات</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button type="button" class="rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700" @click="closePaymentModal">
                            إلغاء
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50"
                            style="background-color:#16a34a;color:#fff"
                            :disabled="paymentForm.processing || available <= 0"
                        >
                            {{ paymentForm.processing ? 'جاري الحفظ...' : 'حفظ الدفعة' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
