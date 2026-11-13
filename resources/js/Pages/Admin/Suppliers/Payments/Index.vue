<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
    balance: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const showPaymentModal = ref(false);
const available = computed(() => Number(props.balance.remaining || 0));

const paymentForm = useForm({
    amount: '',
    paid_on: new Date().toISOString().slice(0, 10),
    method: 'cash',
    notes: '',
});

const methodLabel = {
    cash: 'نقدي',
    transfer: 'تحويل',
    other: 'أخرى',
};

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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

function removePayment(payment) {
    if (!confirm('حذف هذه الدفعة؟')) {
        return;
    }
    router.delete(route('admin.suppliers.payments.destroy', [props.supplier.id, payment.id]), {
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
    <AppLayout title="دفعات المورد">
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('admin.suppliers.show', supplier.id)"
                    class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-gray-100 px-3 py-2 text-sm font-bold text-gray-800"
                >
                    → رجوع
                </Link>
                <h2 class="truncate font-semibold text-xl text-gray-800">دفعات {{ supplier.name }}</h2>
            </div>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6">
                <Link
                    :href="route('admin.suppliers.show', supplier.id)"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-gray-800 shadow-sm sm:hidden"
                >
                    → رجوع إلى المورد
                </Link>

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
                    <button
                        v-if="canManage"
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-bold text-white"
                        style="background-color:#16a34a;color:#fff"
                        @click="openPaymentModal"
                    >
                        إضافة دفعة
                    </button>
                    <Link
                        :href="route('admin.suppliers.show', supplier.id)"
                        class="hidden rounded-lg bg-gray-100 px-4 py-2 text-sm font-bold text-gray-800 sm:inline-flex"
                    >
                        → رجوع إلى المورد
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
