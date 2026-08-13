<script setup>
import { usePwaInstall } from '@/Composables/usePwaInstall';

const {
    showAndroidInstall,
    showIosInstall,
    showUpdate,
    dismiss,
    promptInstall,
    applyUpdate,
} = usePwaInstall();
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[70] flex flex-col gap-2 p-3 sm:p-4">
        <div
            v-if="showUpdate"
            class="pointer-events-auto mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-amber-200 bg-white/95 shadow-xl backdrop-blur"
        >
            <div class="flex items-start gap-3 p-4">
                <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">يتوفر تحديث للتطبيق</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">حدّث الآن للحصول على آخر تحسينات النظام.</p>
                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-green-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-800"
                            @click="applyUpdate"
                        >
                            تحديث الآن
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showAndroidInstall"
            class="pointer-events-auto mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-green-200 bg-white/95 shadow-xl backdrop-blur"
        >
            <div class="flex items-start gap-3 p-4">
                <img src="/icons/icon-96x96.png" alt="" class="h-11 w-11 shrink-0 rounded-xl shadow-sm" width="96" height="96">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">تثبيت نظام الكاشير</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">أضفه لشاشتك الرئيسية وافتحه كتطبيق بسرعة أكبر.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="rounded-lg bg-green-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-green-800"
                            @click="promptInstall"
                        >
                            تثبيت
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200"
                            @click="dismiss"
                        >
                            لاحقاً
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else-if="showIosInstall"
            class="pointer-events-auto mx-auto w-full max-w-md overflow-hidden rounded-2xl border border-green-200 bg-white/95 shadow-xl backdrop-blur"
        >
            <div class="flex items-start gap-3 p-4">
                <img src="/icons/icon-96x96.png" alt="" class="h-11 w-11 shrink-0 rounded-xl shadow-sm" width="96" height="96">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">ثبّت التطبيق على الآيفون</p>
                    <p class="mt-1 text-xs leading-5 text-gray-500">
                        اضغط
                        <span class="inline-flex items-center gap-1 font-bold text-green-700">
                            مشاركة
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5">
                                <path d="M12 2.25a.75.75 0 01.75.75v9.19l2.47-2.47a.75.75 0 111.06 1.06l-3.75 3.75a.75.75 0 01-1.06 0L7.72 10.78a.75.75 0 111.06-1.06l2.47 2.47V3a.75.75 0 01.75-.75z" />
                                <path d="M4.5 15.75a.75.75 0 01.75.75v2.25c0 .414.336.75.75.75h12a.75.75 0 00.75-.75V16.5a.75.75 0 011.5 0v2.25A2.25 2.25 0 0118 21H6a2.25 2.25 0 01-2.25-2.25V16.5a.75.75 0 01.75-.75z" />
                            </svg>
                        </span>
                        ثم اختر
                        <span class="font-bold text-green-700">إضافة إلى الشاشة الرئيسية</span>.
                    </p>
                    <div class="mt-3">
                        <button
                            type="button"
                            class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-200"
                            @click="dismiss"
                        >
                            حسناً
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
