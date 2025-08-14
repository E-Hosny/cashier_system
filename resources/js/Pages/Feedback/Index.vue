<template>
    <AppLayout title="التقييمات">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight text-right">
                <i class="fas fa-star text-yellow-500 mr-2"></i>
                إدارة التقييمات
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- إحصائيات سريعة -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-star text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-4 text-right">
                                <p class="text-sm font-medium text-gray-500">إجمالي التقييمات</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.total }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-chart-line text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-4 text-right">
                                <p class="text-sm font-medium text-gray-500">متوسط التقييم</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.average_rating }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-star text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-4 text-right">
                                <p class="text-sm font-medium text-gray-500">5 نجوم</p>
                                <p class="text-2xl font-semibold text-gray-900">{{ stats.rating_distribution[5] || 0 }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <i class="fas fa-link text-white text-sm"></i>
                                </div>
                            </div>
                            <div class="ml-4 text-right">
                                <p class="text-sm font-medium text-gray-500">رابط التقييم</p>
                                <button @click="copyFeedbackLink" class="text-sm text-blue-600 hover:text-blue-800">
                                    نسخ الرابط
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- توزيع التقييمات -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 text-right">توزيع التقييمات</h3>
                    <div class="space-y-3">
                        <div v-for="rating in 5" :key="rating" class="flex items-center">
                            <span class="w-8 text-sm text-gray-600 text-right">{{ rating }} ⭐</span>
                            <div class="flex-1 mx-4">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-yellow-400 h-2 rounded-full" 
                                         :style="{ width: getRatingPercentage(rating) + '%' }"></div>
                                </div>
                            </div>
                            <span class="w-12 text-sm text-gray-600 text-right">
                                {{ stats.rating_distribution[rating] || 0 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- قائمة التقييمات -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 text-right">التقييمات</h3>
                    </div>
                    
                    <div class="divide-y divide-gray-200">
                        <div v-for="item in feedback.data" :key="item.id" class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 text-right">
                                    <div class="flex items-center mb-2 justify-end">
                                        <div class="ml-3 text-yellow-400">
                                            <span v-for="star in 5" :key="star">
                                                <i :class="star <= item.rating ? 'fas fa-star' : 'far fa-star'"></i>
                                            </span>
                                        </div>
                                        <span class="text-sm text-gray-500 mr-3">{{ item.created_at }}</span>
                                    </div>
                                    
                                    <div v-if="item.comment" class="text-gray-700 mb-3 text-right">
                                        {{ item.comment }}
                                    </div>
                                    
                                    <div class="flex items-center text-sm text-gray-500 justify-end">
                                        <span class="mr-4">
                                            <i class="fas fa-clock mr-1"></i>
                                            {{ item.created_at }}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2 ml-4">
                                    <button v-if="!item.is_approved" 
                                            @click="approveFeedback(item.id)"
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <i class="fas fa-check ml-1"></i>
                                        موافقة
                                    </button>
                                    
                                    <button @click="deleteFeedback(item.id)"
                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash ml-1"></i>
                                        حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <div v-if="feedback.links && feedback.links.length > 3" class="px-6 py-4 border-t border-gray-200">
                        <nav class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <Link v-if="feedback.prev_page_url" 
                                      :href="feedback.prev_page_url"
                                      class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    السابق
                                </Link>
                                <Link v-if="feedback.next_page_url" 
                                      :href="feedback.next_page_url"
                                      class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    التالي
                                </Link>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        عرض
                                        <span class="font-medium">{{ feedback.from }}</span>
                                        إلى
                                        <span class="font-medium">{{ feedback.to }}</span>
                                        من
                                        <span class="font-medium">{{ feedback.total }}</span>
                                        نتيجة
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <Link v-for="(link, key) in feedback.links" 
                                              :key="key"
                                              :href="link.url"
                                              v-html="link.label"
                                              :class="[
                                                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                                  link.url === null 
                                                      ? 'bg-gray-100 border-gray-300 text-gray-400 cursor-default'
                                                      : link.active
                                                          ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                              ]"
                                        />
                                    </nav>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const feedback = computed(() => page.props.feedback);
const stats = computed(() => page.props.stats);

const getRatingPercentage = (rating) => {
    const count = stats.value.rating_distribution[rating] || 0;
    const total = stats.value.total;
    return total > 0 ? Math.round((count / total) * 100) : 0;
};

const copyFeedbackLink = () => {
    const link = `${window.location.origin}/feedback`;
    navigator.clipboard.writeText(link).then(() => {
        alert('تم نسخ رابط التقييم!');
    });
};

const approveFeedback = (id) => {
    if (confirm('هل تريد الموافقة على هذا التقييم؟')) {
        router.put(route('admin.feedback.approve', id));
    }
};

const deleteFeedback = (id) => {
    if (confirm('هل تريد حذف هذا التقييم؟')) {
        router.delete(route('admin.feedback.destroy', id));
    }
};
</script> 