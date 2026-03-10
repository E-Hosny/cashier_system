<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const canViewReports = computed(() => page.props.canViewReports);
const canManageAttendance = computed(() => !!page.props.canManageAttendance);
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Card 1: الكاشير -->
                    <a href="/cashier" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-blue-500 text-4xl mb-4">
                                🏪
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">الكاشير</h3>
                            <p class="text-sm text-gray-500">إدارة عمليات البيع بسهولة</p>
                        </div>
                    </a>

                    <!-- Card 2: المنتجات -->
                    <a href="/products" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-green-500 text-4xl mb-4">
                                📦
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">المنتجات</h3>
                            <p class="text-sm text-gray-500">إدارة المنتجات وتحديثها</p>
                        </div>
                    </a>

                    <!-- Card 3: تقارير المبيعات -->
                    <a v-if="canViewReports" href="/sales-report" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-red-500 text-4xl mb-4">
                                📊
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">تقارير المبيعات</h3>
                            <p class="text-sm text-gray-500">عرض تحليلات وتقارير المبيعات</p>
                        </div>
                    </a>

                    <!-- Card 4: المشتريات -->
                    <a href="/purchases" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-yellow-500 text-4xl mb-4">
                                🛒
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">المشتريات</h3>
                            <p class="text-sm text-gray-500">عرض وإدارة المشتريات</p>
                        </div>
                    </a>

                    <!-- Card 5: المصروفات -->
                    <a href="/expenses" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-purple-500 text-4xl mb-4">
                                💸
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">المصروفات</h3>
                            <p class="text-sm text-gray-500">عرض وإدارة المصروفات</p>
                        </div>
                    </a>

                    <!-- Card 6: الموظفين -->
                    <a v-if="canManageAttendance" href="/employees" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-orange-500 text-4xl mb-4">
                                👥
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">الموظفين</h3>
                            <p class="text-sm text-gray-500">إدارة الحضور والانصراف</p>
                        </div>
                    </a>

                    <!-- Card 6: الفواتير -->
                    <a href="/invoices" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-indigo-500 text-4xl mb-4">
                                🧾
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">الفواتير</h3>
                            <p class="text-sm text-gray-500">عرض فواتير اليوم الحالي</p>
                        </div>
                    </a>

                    <!-- Card 7: التقييمات -->
                    <a v-if="$page.props.auth.user.roles && $page.props.auth.user.roles.includes('admin')" 
                       href="/admin/feedback" 
                       class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                        <div class="flex flex-col items-center">
                            <div class="text-yellow-500 text-4xl mb-4">
                                ⭐
                            </div>
                            <h3 class="text-lg font-semibold text-gray-700">التقييمات</h3>
                            <p class="text-sm text-gray-500">إدارة تقييمات العملاء</p>
                        </div>
                    </a>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
