<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
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
const total = computed(() => props.purchases.reduce((sum, row) => sum + Number(row.total_amount || 0), 0));

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
    router.delete(route('admin.suppliers.purchases.destroy', [props.supplier.id, purchase.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :title="supplier.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">{{ supplier.name }}</h2>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6">
                <div class="flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-lg font-bold text-gray-900">{{ supplier.name }}</div>
                        <div class="text-sm text-gray-500">{{ supplier.is_active ? 'مورد نشط' : 'مورد متوقف' }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link
                            v-if="canPay"
                            :href="route('admin.suppliers.payments.create', supplier.id)"
                            class="rounded-lg px-4 py-2 text-sm font-bold text-white"
                            style="background-color:#16a34a;color:#fff"
                        >
                            إضافة دفعة
                        </Link>
                        <Link
                            v-if="showPayments"
                            :href="route('admin.suppliers.payments.index', supplier.id)"
                            class="rounded-lg bg-amber-100 px-4 py-2 text-sm font-bold text-amber-950"
                        >
                            الدفعات
                        </Link>
                        <Link v-if="canEdit" :href="route('admin.suppliers.purchases.create', supplier.id)" class="rounded-lg px-4 py-2 text-sm font-bold text-white shadow" style="background-color: #1d4ed8; color: #ffffff;">
                            فاتورة جديدة
                        </Link>
                        <Link v-if="canEdit" :href="route('admin.suppliers.edit', supplier.id)" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800">
                            تعديل
                        </Link>
                        <button v-if="canEdit" type="button" class="rounded-lg bg-red-50 px-4 py-2 text-sm font-semibold text-red-700" @click="removeSupplier">
                            حذف
                        </button>
                    </div>
                </div>

                <div v-if="flash.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

                <div v-if="showPayments" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <div class="text-sm text-gray-500">إجمالي الفواتير</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ money(balance.owed) }}</div>
                    </div>
                    <div class="rounded-2xl bg-white p-4 shadow-sm">
                        <div class="text-sm text-gray-500">المدفوع</div>
                        <div class="mt-1 text-2xl font-bold text-green-700">{{ money(balance.paid) }}</div>
                    </div>
                    <div class="rounded-2xl p-4 shadow-sm" :style="Number(balance.remaining) > 0 ? 'background-color:#fef3c7' : 'background-color:#dcfce7'">
                        <div class="text-sm font-semibold">المتبقي</div>
                        <div class="mt-1 text-2xl font-bold">{{ money(balance.remaining) }}</div>
                    </div>
                </div>

                <p v-if="supplier.notes" class="rounded-2xl bg-white p-4 text-sm text-gray-700 shadow-sm">{{ supplier.notes }}</p>

                <div v-if="!purchases.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                    لا توجد فواتير لهذا المورد بعد.
                </div>

                <article v-for="purchase in purchases" :key="purchase.id" class="overflow-hidden rounded-2xl bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900">{{ purchase.purchased_on }}</h3>
                            <p class="text-sm text-gray-500">
                                {{ purchase.invoice_number ? `فاتورة ${purchase.invoice_number}` : 'بدون رقم فاتورة' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-bold text-teal-800">{{ money(purchase.total_amount) }}</span>
                            <a
                                v-if="purchase.has_invoice"
                                :href="route('admin.suppliers.purchases.invoice', [supplier.id, purchase.id])"
                                target="_blank"
                                class="rounded-lg px-3 py-1.5 text-sm font-bold text-white"
                                style="background-color: #16a34a; color: #ffffff;"
                            >
                                فتح الملف
                            </a>
                            <Link
                                v-if="canEdit"
                                :href="route('admin.suppliers.purchases.edit', [supplier.id, purchase.id])"
                                class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-800"
                            >
                                تعديل
                            </Link>
                            <button
                                v-if="canEdit"
                                type="button"
                                class="rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700"
                                @click="removePurchase(purchase)"
                            >
                                حذف
                            </button>
                        </div>
                    </div>
                    <div v-if="purchase.has_invoice || purchase.invoice_original_name" class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                        <div class="text-sm font-semibold text-gray-800">ملف الفاتورة</div>
                        <div v-if="purchase.invoice_original_name" class="mt-1 text-sm text-gray-600">{{ purchase.invoice_original_name }}</div>
                        <a
                            v-if="purchase.has_invoice"
                            :href="route('admin.suppliers.purchases.invoice', [supplier.id, purchase.id])"
                            target="_blank"
                            class="mt-2 inline-flex rounded-lg px-3 py-1.5 text-sm font-bold text-white"
                            style="background-color: #16a34a; color: #ffffff;"
                        >
                            فتح الملف
                        </a>
                        <p v-if="!purchase.has_invoice" class="mt-1 text-sm text-red-600">
                            الملف لم يُحفظ. افتح تعديل الفاتورة وارفعه مرة أخرى.
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="line in purchase.lines" :key="line.id" class="grid grid-cols-1 gap-1 px-4 py-3 text-sm sm:grid-cols-4 sm:items-center">
                            <div>
                                <div class="font-semibold text-gray-900">{{ line.item_name }}</div>
                                <div class="text-xs text-gray-500">{{ line.line_type === 'raw' ? 'مادة خام' : 'بند مخصص' }}</div>
                            </div>
                            <div class="text-gray-700">{{ line.quantity }} {{ line.unit || '' }} × {{ money(line.unit_price) }}</div>
                            <div class="text-gray-600">{{ destinationLabel(line) }}</div>
                            <div class="font-bold text-gray-900 sm:text-left">{{ money(line.line_total) }}</div>
                        </div>
                    </div>
                    <p v-if="purchase.notes" class="border-t border-gray-100 px-4 py-3 text-sm text-gray-600">{{ purchase.notes }}</p>
                </article>

                <Link :href="route('admin.suppliers.index')" class="inline-block text-sm font-semibold text-teal-700">العودة للموردين</Link>
            </div>
        </div>
    </AppLayout>
</template>
