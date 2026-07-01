<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tenantName: String,
    logoUrl: { type: String, default: null },
});

const page = usePage();
const previewUrl = ref(props.logoUrl);
const fileInput = ref(null);

const form = useForm({
    logo: null,
});

watch(() => props.logoUrl, (url) => {
    if (!form.logo) {
        previewUrl.value = url;
    }
});

function onFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) {
        return;
    }

    form.logo = file;
    previewUrl.value = URL.createObjectURL(file);
}

function submitLogo() {
    if (!form.logo) {
        return;
    }

    form.post(route('admin.tenant-settings.logo.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function removeLogo() {
    if (!confirm('هل تريد حذف الشعار الحالي؟')) {
        return;
    }

    router.delete(route('admin.tenant-settings.logo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            previewUrl.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <AppLayout title="الإعدادات">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">⚙️ إعدادات البراند</h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                    {{ page.props.flash.success }}
                </div>

                <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">شعار النظام</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            يظهر الشعار في لوحة التحكم والكاشير والفواتير لحساب <strong>{{ tenantName }}</strong>.
                        </p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-xl border border-dashed border-gray-300 bg-gray-50">
                        <div class="w-40 h-28 flex items-center justify-center bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <img
                                v-if="previewUrl"
                                :src="previewUrl"
                                alt="معاينة الشعار"
                                class="max-w-full max-h-full object-contain p-2"
                            />
                            <span v-else class="text-gray-400 text-sm text-center px-2">لا يوجد شعار</span>
                        </div>

                        <div class="flex-1 w-full space-y-3">
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                                class="block w-full text-sm text-gray-600 file:me-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                @change="onFileChange"
                            />
                            <p class="text-xs text-gray-500">PNG أو JPG أو WebP — بحد أقصى 2 ميجابايت. يُفضّل خلفية شفافة.</p>
                            <div v-if="form.errors.logo" class="text-sm text-red-600">{{ form.errors.logo }}</div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
                                    :disabled="form.processing || !form.logo"
                                    @click="submitLogo"
                                >
                                    حفظ الشعار
                                </button>
                                <button
                                    v-if="logoUrl"
                                    type="button"
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
                                    :disabled="form.processing"
                                    @click="removeLogo"
                                >
                                    حذف الشعار
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
