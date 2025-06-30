<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    users: Array,
    roles: Array,
});

const showDeleteModal = ref(false);
const showResetPasswordModal = ref(false);
const userToDelete = ref(null);
const userToResetPassword = ref(null);

const resetPasswordForm = useForm({
    password: '',
    password_confirmation: '',
});

const deleteUser = (user) => {
    userToDelete.value = user;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    router.delete(route('admin.users.destroy', userToDelete.value.id), {
        onSuccess: () => {
            showDeleteModal.value = false;
            userToDelete.value = null;
        },
    });
};

const resetPassword = (user) => {
    userToResetPassword.value = user;
    showResetPasswordModal.value = true;
};

const confirmResetPassword = () => {
    resetPasswordForm.post(route('admin.users.reset-password', userToResetPassword.value.id), {
        onSuccess: () => {
            showResetPasswordModal.value = false;
            userToResetPassword.value = null;
            resetPasswordForm.reset();
        },
    });
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'admin':
            return 'bg-red-100 text-red-800';
        case 'cashier':
            return 'bg-blue-100 text-blue-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<style>
/* Styles for responsive table */
@media (max-width: 768px) {
    .responsive-table thead {
        display: none;
    }
    .responsive-table tbody,
    .responsive-table tr,
    .responsive-table td {
        display: block;
        width: 100%;
    }
    .responsive-table tr {
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .responsive-table td {
        padding: 1rem 1.5rem;
        position: relative;
        border-bottom: 1px solid #e5e7eb;
    }
    .responsive-table td:last-child {
        border-bottom: none;
    }
    .responsive-table td[data-label]::before {
        content: attr(data-label);
        font-weight: bold;
        position: absolute;
        right: 1.5rem;
    }

    .responsive-table .action-buttons {
        justify-content: flex-start;
        padding-top: 0.5rem;
    }
}
</style>

<template>
    <AppLayout title="إدارة المستخدمين">
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    إدارة المستخدمين
                </h2>
                <PrimaryButton @click="router.visit(route('admin.users.create'))">
                    إضافة مستخدم جديد
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-4 sm:p-6">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 responsive-table">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            الاسم
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            البريد الإلكتروني
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            الأدوار
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            تاريخ الإنشاء
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            الإجراءات
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id">
                                        <td class="px-6 py-4 whitespace-nowrap" data-label="الاسم">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ user.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap" data-label="البريد الإلكتروني">
                                            <div class="text-sm text-gray-900">
                                                {{ user.email }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap" data-label="الأدوار">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="role in user.roles"
                                                    :key="role"
                                                    :class="[
                                                        'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                        getRoleBadgeClass(role)
                                                    ]"
                                                >
                                                    {{ role === 'admin' ? 'مدير' : role === 'cashier' ? 'كاشير' : role }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-label="تاريخ الإنشاء">
                                            {{ user.created_at }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" data-label="الإجراءات">
                                            <div class="flex flex-col sm:flex-row gap-2 action-buttons">
                                                <SecondaryButton
                                                    @click="router.visit(route('admin.users.edit', user.id))"
                                                    class="text-sm justify-center"
                                                >
                                                    تعديل
                                                </SecondaryButton>
                                                <SecondaryButton
                                                    @click="resetPassword(user)"
                                                    class="text-sm justify-center"
                                                >
                                                    إعادة تعيين كلمة المرور
                                                </SecondaryButton>
                                                <DangerButton
                                                    @click="deleteUser(user)"
                                                    class="text-sm justify-center"
                                                >
                                                    حذف
                                                </DangerButton>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="showDeleteModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    تأكيد الحذف
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    هل أنت متأكد من حذف المستخدم "{{ userToDelete?.name }}"؟ هذا الإجراء لا يمكن التراجع عنه.
                </p>
                <div class="flex justify-end space-x-3 space-x-reverse">
                    <SecondaryButton @click="showDeleteModal = false">
                        إلغاء
                    </SecondaryButton>
                    <DangerButton @click="confirmDelete" :disabled="false">
                        حذف
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Reset Password Modal -->
        <Modal :show="showResetPasswordModal" @close="showResetPasswordModal = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 mb-4">
                    إعادة تعيين كلمة المرور
                </h2>
                <p class="text-sm text-gray-600 mb-4">
                    إعادة تعيين كلمة المرور للمستخدم "{{ userToResetPassword?.name }}"
                </p>
                
                <div class="mb-4">
                    <InputLabel for="password" value="كلمة المرور الجديدة" />
                    <TextInput
                        id="password"
                        v-model="resetPasswordForm.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="resetPasswordForm.errors.password" class="mt-2" />
                </div>

                <div class="mb-4">
                    <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                    <TextInput
                        id="password_confirmation"
                        v-model="resetPasswordForm.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="resetPasswordForm.errors.password_confirmation" class="mt-2" />
                </div>

                <div class="flex justify-end space-x-3 space-x-reverse">
                    <SecondaryButton @click="showResetPasswordModal = false">
                        إلغاء
                    </SecondaryButton>
                    <PrimaryButton 
                        @click="confirmResetPassword" 
                        :disabled="resetPasswordForm.processing"
                    >
                        إعادة تعيين
                    </PrimaryButton>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template> 