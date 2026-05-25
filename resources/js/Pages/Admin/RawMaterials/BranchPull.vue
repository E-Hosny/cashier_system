<script setup>
import { ref } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    todayPulls: { type: Array, default: () => [] },
    businessDayLabel: { type: String, default: '' },
    branchName: { type: String, default: '' },
});

const page = usePage();
const labelInput = ref(null);

const form = useForm({
    label_code: '',
});

function submit() {
    form.post(route('admin.raw-materials.branch-pull.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            labelInput.value?.focus();
        },
    });
}
</script>

<template>
    <AppLayout title="سحب المواد الخام">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                سحب المواد الخام
                <span v-if="branchName" class="text-base font-normal text-gray-600">— {{ branchName }}</span>
            </h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-lg bg-green-600 text-white font-semibold">سحب مواد خام</span>
                    <Link
                        :href="route('admin.fridge.pull')"
                        class="px-4 py-2 rounded-lg border border-cyan-400 text-cyan-800 hover:bg-cyan-50 font-semibold"
                    >
                        🧊 سحب للتلاجة
                    </Link>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="bg-green-100 border border-green-300 text-green-900 px-4 py-3 rounded-lg text-sm"
                >
                    {{ page.props.flash.success }}
                </div>

                <div class="bg-white shadow-xl rounded-lg p-6">
                    <p class="text-gray-600 mb-4">
                        امسح الباركود أو أدخل رمز الملصق المُكوَّد من المركز. تُخصم الكمية من المخزون المركزي وتُضاف لمخزون هذا الفرع.
                    </p>
                    <form @submit.prevent="submit" class="space-y-4 max-w-xl">
                        <div>
                            <label for="label_code" class="block text-gray-700 font-semibold mb-1">رمز الملصق</label>
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
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg w-full sm:w-auto disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            تأكيد السحب
                        </button>
                    </form>
                </div>

                <div class="bg-white shadow-xl rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">المسحوبات اليوم</h3>
                    <p v-if="businessDayLabel" class="text-sm text-blue-700 bg-blue-50 rounded-lg px-3 py-2 mb-4">
                        {{ businessDayLabel }}
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-200 text-sm">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="border border-gray-200 p-2">الوقت</th>
                                    <th class="border border-gray-200 p-2">المادة</th>
                                    <th class="border border-gray-200 p-2">القطع</th>
                                    <th class="border border-gray-200 p-2">الكود</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="todayPulls.length === 0">
                                    <td colspan="4" class="text-center p-6 text-gray-500">لا توجد مسحوبات لهذا اليوم.</td>
                                </tr>
                                <tr v-for="row in todayPulls" :key="row.id">
                                    <td class="border border-gray-200 p-2 text-center">{{ row.received_at }}</td>
                                    <td class="border border-gray-200 p-2 text-center">{{ row.product_name }}</td>
                                    <td class="border border-gray-200 p-2 text-center">
                                        {{ row.piece_count }} {{ row.unit }}
                                    </td>
                                    <td class="border border-gray-200 p-2 text-center font-mono text-xs">{{ row.label_code }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
