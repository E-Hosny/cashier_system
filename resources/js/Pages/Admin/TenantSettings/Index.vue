<script setup>
import { ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tenantName: String,
    logoUrl: { type: String, default: null },
    qzKeysConfigured: { type: Boolean, default: false },
    branches: { type: Array, default: () => [] },
});

const page = usePage();
const previewUrl = ref(props.logoUrl);
const fileInput = ref(null);
const branchForms = ref({});
const qzStatus = ref({});
const qzPrinters = ref({});

const form = useForm({
    logo: null,
});

const qzKeysForm = useForm({
    certificate: null,
    private_key: null,
});

function initBranchForms() {
    const next = {};
    props.branches.forEach((branch) => {
        const s = branch.printer_settings || {};
        next[branch.id] = {
            mode: s.mode || 'single',
            method: s.method || 'browser',
            customer_printer: s.customer_printer || '',
            staff_printer: s.staff_printer || '',
            processing: false,
            error: '',
        };
    });
    branchForms.value = next;
}

initBranchForms();

watch(() => props.branches, () => initBranchForms(), { deep: true });

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

async function loadQzPrinters(branchId) {
    qzStatus.value[branchId] = 'loading';
    branchForms.value[branchId].error = '';
    try {
        const { listQzPrinters } = await import('@/utils/qzPrint');
        const printers = await listQzPrinters();
        qzPrinters.value[branchId] = printers;
        qzStatus.value[branchId] = 'ok';
    } catch (error) {
        qzStatus.value[branchId] = 'error';
        branchForms.value[branchId].error = 'تعذر الاتصال بـ QZ Tray. تأكد أن البرنامج يعمل على هذا الجهاز.';
        console.error(error);
    }
}

function onQzCertificateChange(e) {
    qzKeysForm.certificate = e.target.files?.[0] || null;
}

function onQzPrivateKeyChange(e) {
    qzKeysForm.private_key = e.target.files?.[0] || null;
}

function submitQzKeys() {
    if (!qzKeysForm.certificate || !qzKeysForm.private_key) {
        return;
    }

    qzKeysForm.post(route('admin.tenant-settings.qz-keys.upload'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => qzKeysForm.reset(),
    });
}

function saveBranchPrinters(branch) {
    const row = branchForms.value[branch.id];
    if (!row) return;

    row.error = '';
    row.processing = true;

    router.put(route('admin.tenant-settings.branches.printers.update', branch.id), {
        mode: row.mode,
        method: row.method,
        customer_printer: row.customer_printer || null,
        staff_printer: row.staff_printer || null,
    }, {
        preserveScroll: true,
        onError: (errors) => {
            row.error = Object.values(errors).flat().join(' ');
        },
        onFinish: () => {
            row.processing = false;
        },
    });
}
</script>

<template>
    <AppLayout title="الإعدادات">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">⚙️ إعدادات النظام</h2>
        </template>

        <div class="py-12" dir="rtl">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div v-if="page.props.flash?.success" class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                    {{ page.props.flash.success }}
                </div>

                <!-- شعار البراند -->
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
                            <p class="text-xs text-gray-500">PNG أو JPG أو WebP — بحد أقصى 2 ميجابايت.</p>
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

                <!-- ثقة QZ Tray (مرة واحدة على جهاز الكاشير) -->
                <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">🔐 ثقة QZ Tray (إيقاف نافذة الموافقة)</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            التوقيع يعمل لكن QZ يظهر «Untrusted website» حتى تثبّت الشهادة على <strong>جهاز الكاشير</strong> — مرة واحدة فقط.
                        </p>
                    </div>

                    <div class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 space-y-2">
                        <p class="font-semibold">الطريقة الموصى بها:</p>
                        <ol class="list-decimal list-inside space-y-1">
                            <li>حمّل حزمة التثبيت أدناه وافك الضغط على جهاز الكاشير</li>
                            <li>شغّل <code class="bg-white px-1 rounded text-xs">install-qz-trust.bat</code> كمسؤول (Run as administrator)</li>
                            <li>أعد تشغيل QZ Tray (Exit ثم افتحه من جديد)</li>
                        </ol>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a
                            v-if="qzKeysConfigured"
                            :href="route('admin.tenant-settings.qz-trust-package.download')"
                            class="inline-flex items-center px-4 py-2 bg-cyan-700 text-white rounded-lg text-sm hover:bg-cyan-800"
                        >
                            ⬇️ تحميل حزمة تثبيت الثقة (ZIP)
                        </a>
                        <span v-else class="text-sm text-red-600">مفاتيح QZ غير موجودة على السيرفر. نفّذ: php artisan qz:generate-keys</span>
                    </div>

                    <details class="text-sm text-gray-700 border border-gray-200 rounded-lg p-4">
                        <summary class="cursor-pointer font-medium">بديل: مفاتيح من Site Manager</summary>
                        <ol class="list-decimal list-inside mt-3 space-y-1 text-gray-600">
                            <li>QZ Tray → Advanced → Site Manager → + → Create New → Yes لكل الخيارات</li>
                            <li>من مجلد «QZ Tray Demo Cert» على سطح المكتب ارفع الملفين هنا:</li>
                        </ol>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">digital-certificate.txt</label>
                                <input type="file" accept=".txt,.pem,.crt" class="w-full text-sm" @change="onQzCertificateChange" />
                                <div v-if="qzKeysForm.errors.certificate" class="text-red-600 text-xs mt-1">{{ qzKeysForm.errors.certificate }}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">private-key.pem</label>
                                <input type="file" accept=".pem,.key" class="w-full text-sm" @change="onQzPrivateKeyChange" />
                                <div v-if="qzKeysForm.errors.private_key" class="text-red-600 text-xs mt-1">{{ qzKeysForm.errors.private_key }}</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="mt-3 px-4 py-2 bg-gray-800 text-white rounded-lg text-sm hover:bg-gray-900 disabled:opacity-50"
                            :disabled="qzKeysForm.processing || !qzKeysForm.certificate || !qzKeysForm.private_key"
                            @click="submitQzKeys"
                        >
                            {{ qzKeysForm.processing ? 'جاري الرفع...' : 'رفع مفاتيح QZ' }}
                        </button>
                    </details>
                </div>

                <!-- إعدادات الطباعة لكل فرع -->
                <div class="bg-white shadow-xl sm:rounded-lg p-6 space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">🖨️ إعدادات الطباعة (QZ Tray)</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            حدّد لكل فرع طابعة واحدة أو طابعتين (زبون + عامل). يجب تثبيت
                            <a href="https://qz.io/download/" target="_blank" rel="noopener" class="text-blue-600 underline">QZ Tray</a>
                            على جهاز الكاشير.
                        </p>
                    </div>

                    <div v-if="!branches.length" class="text-sm text-gray-500 text-center py-6">
                        لا توجد فروع نشطة. أنشئ فرعاً أولاً من صفحة الفروع.
                    </div>

                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="border border-gray-200 rounded-xl p-5 space-y-4"
                    >
                        <div class="flex flex-wrap justify-between items-center gap-2">
                            <h4 class="font-bold text-gray-800">📍 {{ branch.name }}</h4>
                            <button
                                type="button"
                                class="text-sm text-cyan-700 hover:text-cyan-900 underline"
                                @click="loadQzPrinters(branch.id)"
                            >
                                {{ qzStatus[branch.id] === 'loading' ? 'جاري الاتصال...' : 'جلب أسماء الطابعات من QZ' }}
                            </button>
                        </div>

                        <div v-if="branchForms[branch.id]" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">طريقة الطباعة</label>
                                    <select v-model="branchForms[branch.id].method" class="w-full border rounded-lg p-2.5">
                                        <option value="browser">متصفح (الوضع الحالي)</option>
                                        <option value="qz">QZ Tray (طابعة محددة)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">عدد الطابعات</label>
                                    <select v-model="branchForms[branch.id].mode" class="w-full border rounded-lg p-2.5">
                                        <option value="single">طابعة واحدة (زبون فقط)</option>
                                        <option value="dual">طابعتان (زبون + عامل)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">طابعة الزبون</label>
                                    <input
                                        v-if="!qzPrinters[branch.id]?.length"
                                        v-model="branchForms[branch.id].customer_printer"
                                        type="text"
                                        class="w-full border rounded-lg p-2.5"
                                        placeholder="اسم الطابعة كما يظهر في Windows"
                                        :disabled="branchForms[branch.id].method !== 'qz'"
                                    />
                                    <select
                                        v-else
                                        v-model="branchForms[branch.id].customer_printer"
                                        class="w-full border rounded-lg p-2.5"
                                        :disabled="branchForms[branch.id].method !== 'qz'"
                                    >
                                        <option value="">— اختر طابعة —</option>
                                        <option v-for="p in qzPrinters[branch.id]" :key="'c-' + p" :value="p">{{ p }}</option>
                                    </select>
                                </div>
                                <div v-if="branchForms[branch.id].mode === 'dual'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">طابعة العامل</label>
                                    <input
                                        v-if="!qzPrinters[branch.id]?.length"
                                        v-model="branchForms[branch.id].staff_printer"
                                        type="text"
                                        class="w-full border rounded-lg p-2.5"
                                        placeholder="اسم الطابعة الثانية"
                                        :disabled="branchForms[branch.id].method !== 'qz'"
                                    />
                                    <select
                                        v-else
                                        v-model="branchForms[branch.id].staff_printer"
                                        class="w-full border rounded-lg p-2.5"
                                        :disabled="branchForms[branch.id].method !== 'qz'"
                                    >
                                        <option value="">— اختر طابعة —</option>
                                        <option v-for="p in qzPrinters[branch.id]" :key="'s-' + p" :value="p">{{ p }}</option>
                                    </select>
                                </div>
                            </div>

                            <p v-if="branchForms[branch.id].mode === 'dual'" class="text-xs text-gray-500">
                                عند إصدار الفاتورة: تُطبع «نسخة الزبون» على الطابعة الأولى و«نسخة العامل» على الثانية.
                            </p>

                            <div v-if="branchForms[branch.id].error" class="text-sm text-red-600">
                                {{ branchForms[branch.id].error }}
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="button"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-medium disabled:opacity-50"
                                    :disabled="branchForms[branch.id].processing"
                                    @click="saveBranchPrinters(branch)"
                                >
                                    {{ branchForms[branch.id].processing ? 'جاري الحفظ...' : 'حفظ إعدادات الفرع' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
