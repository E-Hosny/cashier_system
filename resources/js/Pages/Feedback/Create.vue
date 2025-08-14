<template>
    <AppLayout title="إضافة تقييم جديد">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight text-right">
                <i class="fas fa-plus text-green-500 mr-2"></i>
                إضافة تقييم جديد
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submitForm">

                        <div class="mb-6 text-right">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                التقييم
                            </label>
                            <div class="flex space-x-2 justify-end">
                                <button v-for="rating in 5" 
                                        :key="rating"
                                        type="button"
                                        @click="form.rating = rating"
                                        :class="[
                                            'w-10 h-10 rounded-full border-2 flex items-center justify-center text-lg',
                                            form.rating >= rating 
                                                ? 'border-yellow-400 bg-yellow-400 text-white' 
                                                : 'border-gray-300 text-gray-400 hover:border-yellow-300'
                                        ]">
                                    ⭐
                                </button>
                            </div>
                            <div v-if="errors.rating" class="text-red-500 text-sm mt-1 text-right">{{ errors.rating }}</div>
                        </div>

                        <div class="mb-6 text-right">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                التعليق
                            </label>
                            <textarea v-model="form.comment" 
                                      rows="4" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-right"></textarea>
                            <div v-if="errors.comment" class="text-red-500 text-sm mt-1 text-right">{{ errors.comment }}</div>
                        </div>

                        <div class="flex items-center justify-end space-x-3">
                            <Link :href="route('admin.feedback.index')"
                                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                إلغاء
                            </Link>
                            <button type="submit" 
                                    class="px-4 py-2 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                حفظ التقييم
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    rating: 5,
    comment: ''
});

const submitForm = () => {
    form.post(route('admin.feedback.store'));
};
</script> 