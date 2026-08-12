<template>
    <AppLayout title="طلبات التوظيف">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight text-right">
                <i class="fas fa-briefcase text-indigo-600 mr-2"></i>
                طلبات التوظيف
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-indigo-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-users text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="mr-4 text-right">
                                <p class="text-sm font-medium text-gray-500">إجمالي المتقدمين</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ totalApplications }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <button
                                type="button"
                                @click="copyPublicLink"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition"
                            >
                                <i class="fas fa-copy ml-2"></i>
                                نسخ رابط التقديم
                            </button>
                            <div class="text-right flex-1 mr-4">
                                <p class="text-sm font-medium text-gray-500">رابط عام للمتقدمين</p>
                                <p class="text-xs text-gray-400 mt-1 break-all" dir="ltr">{{ publicFormUrl }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 text-right">قائمة المتقدمين</h3>
                    </div>

                    <div v-if="applications.data.length === 0" class="p-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p>لا توجد طلبات توظيف حتى الآن.</p>
                        <p class="text-sm mt-2">شارك رابط التقديم مع المتقدمين.</p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table dir="rtl" class="min-w-full divide-y divide-gray-200 text-center">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">الاسم</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">السن</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">العنوان</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">رقم التليفون</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">تاريخ التقديم</th>
                                    <th class="px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider text-center">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="application in applications.data" :key="application.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-center">
                                        {{ application.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-center">
                                        {{ application.age ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-700 max-w-xs text-center">
                                        {{ application.address }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 text-center" dir="ltr">
                                        <a :href="'tel:' + application.phone" class="text-indigo-600 hover:text-indigo-800">
                                            {{ application.phone }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        {{ formatDate(application.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                        <button
                                            type="button"
                                            @click="deleteApplication(application.id)"
                                            class="text-red-600 hover:text-red-800"
                                            title="حذف"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="applications.data.length > 0 && applications.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link
                                    v-if="applications.prev_page_url"
                                    :href="applications.prev_page_url"
                                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    السابق
                                </Link>
                                <Link
                                    v-if="applications.next_page_url"
                                    :href="applications.next_page_url"
                                    class="mr-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                >
                                    التالي
                                </Link>
                            </div>
                            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                                <p class="text-sm text-gray-700">
                                    عرض
                                    <span class="font-medium">{{ applications.from }}</span>
                                    إلى
                                    <span class="font-medium">{{ applications.to }}</span>
                                    من
                                    <span class="font-medium">{{ applications.total }}</span>
                                    نتيجة
                                </p>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <Link
                                        v-for="(link, key) in applications.links"
                                        :key="key"
                                        :href="link.url"
                                        v-html="link.label"
                                        :class="[
                                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                            link.url === null
                                                ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-default'
                                                : link.active
                                                    ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                        ]"
                                    />
                                </nav>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const applications = computed(() => page.props.applications);
const totalApplications = computed(() => page.props.totalApplications ?? 0);
const publicFormUrl = computed(() => page.props.publicFormUrl || `${window.location.origin}/jobs/apply`);

const formatDate = (dateString) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleString('ar-EG', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const copyPublicLink = () => {
    navigator.clipboard.writeText(publicFormUrl.value).then(() => {
        alert('تم نسخ رابط التقديم على الوظيفة!');
    });
};

const deleteApplication = (id) => {
    if (confirm('هل تريد حذف هذا الطلب؟')) {
        router.delete(route('admin.job-applications.destroy', id));
    }
};
</script>
