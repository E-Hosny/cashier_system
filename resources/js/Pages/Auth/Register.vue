<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="إنشاء حساب جديد" />

    <div class="min-h-screen flex flex-col justify-center items-center bg-gradient-to-br from-yellow-100 via-green-100 to-white">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8 border-t-8 border-blue-400">
            <div class="flex flex-col items-center mb-6">
                <AuthenticationCardLogo />
                <h1 class="mt-4 text-2xl font-bold text-blue-700">إنشاء حساب جديد</h1>
                <p class="text-gray-500 mt-1">انضم إلى نظام عصائر عم حسني</p>
            </div>

            <form @submit.prevent="submit" dir="rtl">
                <div>
                    <InputLabel for="name" value="الاسم الكامل" class="font-bold" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="أدخل اسمك الكامل"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="mt-4">
                    <InputLabel for="email" value="البريد الإلكتروني" class="font-bold" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        required
                        autocomplete="username"
                        placeholder="example@email.com"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="كلمة المرور" class="font-bold" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password_confirmation" value="تأكيد كلمة المرور" class="font-bold" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>

                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="mt-4">
                    <label class="flex items-center cursor-pointer select-none">
                        <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />
                        <span class="ms-2 text-sm text-gray-700">
                            أوافق على <a target="_blank" :href="route('terms.show')" class="underline text-blue-600 hover:text-blue-800">شروط الخدمة</a> و <a target="_blank" :href="route('policy.show')" class="underline text-blue-600 hover:text-blue-800">سياسة الخصوصية</a>
                        </span>
                    </label>
                    <InputError class="mt-2" :message="form.errors.terms" />
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between mt-6 gap-2">
                    <Link :href="route('login')" class="underline text-sm text-blue-700 hover:text-blue-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-400">
                        لديك حساب بالفعل؟
                    </Link>

                    <PrimaryButton class="w-full sm:w-auto bg-blue-500 hover:bg-blue-600 border-0" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                        إنشاء الحساب
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
