<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    branches: Array,
});

const form = useForm({
    name: '',
    is_active: true,
});

const submit = () => {
    form.post(route('admin.branches.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name');
            form.is_active = true;
        },
    });
};

const showEditModal = ref(false);
const branchBeingEdited = ref(null);

const editForm = useForm({
    name: '',
    is_active: true,
});

function openEdit(branch) {
    branchBeingEdited.value = branch;
    editForm.name = branch.name;
    editForm.is_active = !!branch.is_active;
    editForm.clearErrors();
    showEditModal.value = true;
}

function closeEdit() {
    showEditModal.value = false;
    branchBeingEdited.value = null;
}

function saveEdit() {
    if (!branchBeingEdited.value) return;
    editForm.put(route('admin.branches.update', branchBeingEdited.value.id), {
        preserveScroll: true,
        onSuccess: () => closeEdit(),
    });
}
</script>

<template>
    <AppLayout title="الفروع">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">إدارة الفروع</h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <div class="bg-white shadow-xl sm:rounded-lg p-6">
                    <h3 class="font-semibold text-lg mb-4">إضافة فرع جديد</h3>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-end">
                            <div class="flex-1 w-full">
                                <InputLabel for="name" value="اسم الفرع" />
                                <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>
                            <PrimaryButton type="submit" :disabled="form.processing">حفظ الفرع</PrimaryButton>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <Checkbox v-model:checked="form.is_active" />
                            <span class="text-sm text-gray-700">الفرع نشط (يظهر في قائمة الدخول للفروع)</span>
                        </label>
                        <InputError :message="form.errors.is_active" class="mt-1" />
                    </form>
                </div>

                <div class="bg-white shadow-xl sm:rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الاسم</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الإنشاء</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="b in branches" :key="b.id">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ b.name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span
                                        :class="[
                                            'inline-flex px-2 py-0.5 rounded-full text-xs font-medium',
                                            b.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700',
                                        ]"
                                    >
                                        {{ b.is_active ? 'نشط' : 'موقوف' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ b.created_at }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <SecondaryButton type="button" class="text-sm py-1 px-3" @click="openEdit(b)">
                                        تعديل
                                    </SecondaryButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <SecondaryButton type="button" @click="router.visit(route('dashboard'))">
                    العودة للوحة التحكم
                </SecondaryButton>
            </div>
        </div>

        <Modal :show="showEditModal" @close="closeEdit">
            <div class="p-6" v-if="branchBeingEdited">
                <h2 class="text-lg font-medium text-gray-900 mb-4">تعديل الفرع</h2>
                <form class="space-y-4" @submit.prevent="saveEdit">
                    <div>
                        <InputLabel for="edit_name" value="اسم الفرع" />
                        <TextInput
                            id="edit_name"
                            v-model="editForm.name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError class="mt-2" :message="editForm.errors.name" />
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <Checkbox v-model:checked="editForm.is_active" />
                        <span class="text-sm text-gray-700">الفرع نشط</span>
                    </label>
                    <InputError :message="editForm.errors.is_active" class="mt-1" />
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton type="button" @click="closeEdit">إلغاء</SecondaryButton>
                        <PrimaryButton type="submit" :disabled="editForm.processing">حفظ التعديلات</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AppLayout>
</template>
