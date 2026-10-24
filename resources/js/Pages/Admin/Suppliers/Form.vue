<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supplier: { type: Object, default: null },
});

const page = usePage();
const editing = computed(() => !!props.supplier?.id);

const form = useForm({
    name: props.supplier?.name || '',
    contact_person: props.supplier?.contact_person || '',
    phone: props.supplier?.phone || '',
    email: props.supplier?.email || '',
    notes: props.supplier?.notes || '',
    is_active: props.supplier ? !!props.supplier.is_active : true,
});

function submit() {
    if (editing.value) {
        form.put(route('admin.suppliers.update', props.supplier.id));
        return;
    }
    form.post(route('admin.suppliers.store'));
}
</script>

<template>
    <AppLayout :title="editing ? 'تعديل مورد' : 'مورد جديد'">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">{{ editing ? 'تعديل مورد' : 'مورد جديد' }}</h2>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <form class="mx-auto max-w-2xl space-y-4 px-4 sm:px-6" @submit.prevent="submit">
                <div class="space-y-4 rounded-2xl bg-white p-5 shadow-sm">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">اسم المورد *</label>
                        <input v-model="form.name" required class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">مسؤول التواصل</label>
                            <input v-model="form.contact_person" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">الهاتف</label>
                            <input v-model="form.phone" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">البريد</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">ملاحظات</label>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2.5" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-800">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300" />
                        المورد نشط
                    </label>
                </div>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Link
                        :href="editing ? route('admin.suppliers.show', supplier.id) : route('admin.suppliers.index')"
                        class="rounded-lg bg-gray-100 px-4 py-2.5 text-center text-sm font-semibold text-gray-700"
                    >
                        إلغاء
                    </Link>
                    <button
                        type="submit"
                        class="rounded-lg bg-green-600 px-4 py-2.5 text-sm font-bold text-white shadow disabled:opacity-50"
                        style="background-color: #16a34a; color: #ffffff;"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'جاري الحفظ...' : 'حفظ' }}
                    </button>
                </div>
                <p v-if="page.props.flash?.error" class="text-sm text-red-600">{{ page.props.flash.error }}</p>
            </form>
        </div>
    </AppLayout>
</template>
