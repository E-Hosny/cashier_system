<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, usePage, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { translateSize } from '@/utils/productSizes';

const props = defineProps({
    todayPulls: { type: Array, default: () => [] },
    selectedDate: { type: String, default: '' },
    maxBusinessDay: { type: String, default: '' },
    businessDayLabel: { type: String, default: '' },
    branchName: { type: String, default: '' },
});

const page = usePage();
const labelInput = ref(null);
const filterDate = ref(props.selectedDate || props.maxBusinessDay || '');

watch(
    () => props.selectedDate,
    (v) => {
        if (v) filterDate.value = v;
    }
);

const branchPullHref = computed(() =>
    route('admin.raw-materials.branch-pull', filterDate.value ? { date: filterDate.value } : {})
);

function applyDateFilter() {
    if (!filterDate.value) return;
    router.get(
        route('admin.fridge.pull'),
        { date: filterDate.value },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

const form = useForm({ label_code: '' });

function submit() {
    form.post(route('admin.fridge.pull.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            labelInput.value?.focus();
        },
    });
}
</script>

<template>
    <AppLayout title="سحب منتجات التلاجة">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                🧊 سحب إلى التلاجة
                <span v-if="branchName" class="text-base font-normal text-gray-600">— {{ branchName }}</span>
            </h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-wrap gap-2 no-print">
                    <Link
                        :href="branchPullHref"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50"
                    >
                        سحب مواد خام
                    </Link>
                    <span class="px-4 py-2 rounded-lg bg-cyan-700 text-white font-semibold">سحب للتلاجة</span>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="bg-green-100 border border-green-300 text-green-900 px-4 py-3 rounded-lg text-sm"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="bg-white shadow-xl rounded-lg p-6">
                    <p class="text-gray-600 mb-4">
                        امسح باركود المنتج المُكوَّد من المركز. تُضاف الوحدات لتلاجة الفرع فقط — خصم المقادير يتم عند البيع من الكاشير.
                    </p>
                    <form class="space-y-4 max-w-xl" @submit.prevent="submit">
                        <div>
                            <label for="label_code" class="block text-gray-700 font-semibold mb-1">رمز الملصق (FR-…)</label>
                            <input
                                id="label_code"
                                ref="labelInput"
                                v-model="form.label_code"
                                type="text"
                                class="w-full border-gray-300 rounded-md shadow-sm font-mono p-3"
                                autocomplete="off"
                                required
                            />
                            <p v-if="form.errors.label_code" class="text-red-600 text-sm mt-1">{{ form.errors.label_code }}</p>
                        </div>
                        <button
                            type="submit"
                            class="bg-cyan-600 hover:bg-cyan-700 text-white font-bold py-3 px-6 rounded-lg disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            تأكيد السحب للتلاجة
                        </button>
                    </form>
                </div>

                <div class="bg-white shadow-xl rounded-lg p-6">
                    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">مسحوبات التلاجة</h3>
                        <div class="flex flex-wrap items-center gap-2">
                            <label for="pull_date" class="text-sm font-medium text-gray-700 whitespace-nowrap">يوم العمل:</label>
                            <input
                                id="pull_date"
                                v-model="filterDate"
                                type="date"
                                class="border border-gray-300 rounded-lg p-2 text-sm"
                                :max="maxBusinessDay"
                                @change="applyDateFilter"
                            />
                        </div>
                    </div>
                    <p v-if="businessDayLabel" class="text-sm text-cyan-700 bg-cyan-50 rounded-lg px-3 py-2 mb-4">
                        {{ businessDayLabel }}
                    </p>
                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border p-2">الوقت</th>
                                <th class="border p-2">المنتج</th>
                                <th class="border p-2">الوحدات</th>
                                <th class="border p-2">الكود</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!todayPulls.length">
                                <td colspan="4" class="text-center p-6 text-gray-500">لا توجد مسحوبات في يوم العمل المحدد.</td>
                            </tr>
                            <tr v-for="pull in todayPulls" :key="pull.id">
                                <td class="border p-2 text-center align-top">{{ pull.received_at }}</td>
                                <td class="border p-2 text-center align-top">
                                    <ul v-if="pull.lines?.length" class="text-xs text-right space-y-1">
                                        <li v-for="(ln, i) in pull.lines" :key="i">
                                            {{ ln.product_name }}
                                            <span v-if="ln.size" class="text-gray-500">({{ translateSize(ln.size) }})</span>
                                        </li>
                                    </ul>
                                    <template v-else>
                                        {{ pull.product_name }}
                                        <span v-if="pull.size" class="text-gray-500 text-xs">({{ translateSize(pull.size) }})</span>
                                    </template>
                                </td>
                                <td class="border p-2 text-center align-top">
                                    <ul v-if="pull.lines?.length" class="text-xs space-y-1">
                                        <li v-for="(ln, i) in pull.lines" :key="i">{{ ln.unit_count }}</li>
                                    </ul>
                                    <template v-else>{{ pull.unit_count }}</template>
                                </td>
                                <td class="border p-2 text-center font-mono text-xs align-top">{{ pull.label_code }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
