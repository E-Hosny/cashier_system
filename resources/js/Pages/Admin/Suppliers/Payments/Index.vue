<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    balance: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

const methodLabel = {
    cash: 'نقدي',
    transfer: 'تحويل',
    other: 'أخرى',
};

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function removePayment(payment) {
    if (!confirm('حذف هذه الدفعة؟')) {
        return;
    }
    router.delete(route('admin.suppliers.payments.destroy', [props.supplier.id, payment.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout title="دفعات المورد">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">دفعات {{ supplier.name }}</h2>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6">
                <div v-if="flash.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ flash.success }}</div>
                <div v-if="flash.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ flash.error }}</div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="canManage"
                        :href="route('admin.suppliers.payments.create', supplier.id)"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-white"
                        style="background-color:#16a34a;color:#fff"
                    >
                        إضافة دفعة
                    </Link>
                    <Link :href="route('admin.suppliers.show', supplier.id)" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-800">
                        العودة للمورد
                    </Link>
                </div>

                <div v-if="!payments.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                    لا توجد دفعات مسجلة.
                </div>

                <article v-for="payment in payments" :key="payment.id" class="rounded-2xl bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-bold text-gray-900">{{ payment.paid_on }}</div>
                            <div class="text-sm text-gray-500">{{ methodLabel[payment.method] || 'بدون طريقة' }}</div>
                            <p v-if="payment.notes" class="mt-1 text-sm text-gray-600">{{ payment.notes }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-lg font-bold text-green-700">{{ money(payment.amount) }}</span>
                            <Link
                                v-if="canManage"
                                :href="route('admin.suppliers.payments.edit', [supplier.id, payment.id])"
                                class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-800"
                            >
                                تعديل
                            </Link>
                            <button
                                v-if="canManage"
                                type="button"
                                class="rounded-lg bg-red-50 px-3 py-1.5 text-sm font-semibold text-red-700"
                                @click="removePayment(payment)"
                            >
                                حذف
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
