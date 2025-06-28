<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            form.reset();
        },
    });
};

const toggleRole = (role) => {
    const index = form.roles.indexOf(role);
    if (index > -1) {
        form.roles.splice(index, 1);
    } else {
        form.roles.push(role);
    }
};

const getRoleDisplayName = (role) => {
    switch (role) {
        case 'admin':
            return 'مدير';
        case 'cashier':
            return 'كاشير';
        default:
            return role;
    }
};
</script>

<template>
    <AppLayout title="إضافة مستخدم جديد">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    إضافة مستخدم جديد
                </h2>
                <SecondaryButton @click="$inertia.visit(route('admin.users.index'))">
                    العودة للقائمة
                </SecondaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit">
                            <!-- الاسم -->
                            <div class="mb-6">
                                <InputLabel for="name" value="الاسم" />
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    autofocus
                                />
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <!-- البريد الإلكتروني -->
                            <div class="mb-6">
                                <InputLabel for="email" value="البريد الإلكتروني" />
                                <TextInput
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.email" class="mt-2" />
                            </div>

                            <!-- كلمة المرور -->
                            <div class="mb-6">
                                <InputLabel for="password" value="كلمة المرور" />
                                <TextInput
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.password" class="mt-2" />
                            </div>

                            <!-- تأكيد كلمة المرور -->
                            <div class="mb-6">
                                <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" />
                                <TextInput
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.password_confirmation" class="mt-2" />
                            </div>

                            <!-- الأدوار -->
                            <div class="mb-6">
                                <InputLabel value="الأدوار" />
                                <div class="mt-2 space-y-2">
                                    <div v-for="role in roles" :key="role" class="flex items-center">
                                        <Checkbox
                                            :id="'role_' + role"
                                            :checked="form.roles.includes(role)"
                                            @change="toggleRole(role)"
                                        />
                                        <label :for="'role_' + role" class="mr-2 text-sm text-gray-700">
                                            {{ getRoleDisplayName(role) }}
                                        </label>
                                    </div>
                                </div>
                                <InputError :message="form.errors.roles" class="mt-2" />
                            </div>

                            <!-- أزرار الإجراءات -->
                            <div class="flex items-center justify-end space-x-3 space-x-reverse">
                                <SecondaryButton
                                    type="button"
                                    @click="$inertia.visit(route('admin.users.index'))"
                                >
                                    إلغاء
                                </SecondaryButton>
                                <PrimaryButton
                                    type="submit"
                                    :disabled="form.processing"
                                    :class="{ 'opacity-25': form.processing }"
                                >
                                    إنشاء المستخدم
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 