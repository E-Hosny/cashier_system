<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, required: true },
    payment: { type: Object, default: null },
    balance: { type: Object, required: true },
    available: { type: Number, default: 0 },
});

const editing = computed(() => !!props.payment?.id);

const form = useForm({
    amount: props.payment?.amount ?? (props.available > 0 ? props.available : ''),
    paid_on: props.payment?.paid_on || new Date().toISOString().slice(0, 10),
    method: props.payment?.method || 'cash',
    notes: props.payment?.notes || '',
});

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function submit() {
    if (editing.value) {
        form.put(route('admin.suppliers.payments.update', [props.supplier.id, props.payment.id]));
        return;
    }
    form.post(route('admin.suppliers.payments.store', props.supplier.id));
}
</script>

<template>
    <AppLayout :title="editing ? 'تعديل دفعة' : 'إضافة دفعة'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">{{ editing ? 'تعديل دفعة' : 'إضافة دفعة' }}</h2>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <form class="mx-auto max-w-xl space-y-4 px-4 sm:px-6" @submit.prevent="submit">
                <div class="rounded-2xl p-4 shadow-sm" style="background-color:#fef3c7">
                    <div class="text-sm font-semibold text-gray-700">المتبقي على {{ supplier.name }}</div>
                    <div class="mt-1 text-3xl font-bold text-gray-900">{{ money(balance.remaining) }}</div>
                    <div class="mt-1 text-sm text-gray-600">يمكن سداد حتى {{ money(available) }}</div>
                </div>

                <div class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">المبلغ *</label>
                        <input v-model="form.amount" type="number" min="0.01" :max="available" step="0.01" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        <p v-if="form.errors.amount" class="mt-1 text-sm text-red-600">{{ form.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">تاريخ الدفعة *</label>
                        <input v-model="form.paid_on" type="date" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">طريقة الدفع</label>
                        <select v-model="form.method" class="w-full rounded-lg border border-gray-300 px-3 py-2.5">
                            <option value="cash">نقدي</option>
                            <option value="transfer">تحويل</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ملاحظات</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Link :href="route('admin.suppliers.payments.index', supplier.id)" class="rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm font-semibold text-gray-700">
                        إلغاء
                    </Link>
                    <button type="submit" class="rounded-lg px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50" style="background-color:#16a34a;color:#fff" :disabled="form.processing || available <= 0">
                        {{ form.processing ? 'جاري الحفظ...' : 'حفظ الدفعة' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
