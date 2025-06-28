<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

function handleImageError() {
    document.getElementById('screenshot-container')?.classList.add('!hidden');
    document.getElementById('docs-card')?.classList.add('!row-span-1');
    document.getElementById('docs-card-content')?.classList.add('!flex-row');
    document.getElementById('background')?.classList.add('!hidden');
}
</script>

<template>
    <Head title="مرحباً بك في عصائر عم حسني" />
    <div class="min-h-screen flex flex-col justify-center items-center bg-gradient-to-br from-yellow-100 via-green-100 to-white">
        <div class="w-full max-w-xl bg-white rounded-2xl shadow-lg p-10 border-t-8 border-green-400 flex flex-col items-center mt-10">
            <AuthenticationCardLogo />
            <h1 class="mt-6 text-3xl font-extrabold text-green-700 text-center">مرحباً بك في نظام إدارة عصائر عم حسني</h1>
            <p class="mt-2 text-lg text-gray-600 text-center">نظام احترافي لإدارة المبيعات والمخزون والمشتريات لمتاجر العصائر</p>
            <div class="mt-8 w-full flex flex-col items-center gap-4">
                <Link v-if="canLogin && !$page.props.auth.user" :href="route('login')" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 rounded-lg text-xl shadow transition flex items-center justify-center">
                    دخول للنظام
                </Link>
                <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="w-full bg-yellow-400 hover:bg-yellow-500 text-green-900 font-bold py-3 rounded-lg text-xl shadow transition">
                    الذهاب للوحة التحكم
                </Link>
            </div>
            <footer class="mt-10 text-center text-sm text-gray-400">
                جميع الحقوق محفوظة &copy; {{ new Date().getFullYear() }} عصائر عم حسني
            </footer>
        </div>
    </div>
</template>
