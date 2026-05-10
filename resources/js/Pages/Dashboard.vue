<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const canViewReports = computed(() => page.props.canViewReports);
const canManageAttendance = computed(() => !!page.props.canManageAttendance);
const canManageFeedback = computed(() => !!page.props.canManageFeedback);
const branchContext = computed(() => page.props.branchContext || {});

const roles = computed(() => page.props?.auth?.user?.roles || []);
const isSuperAdmin = computed(() => roles.value.includes('super admin'));
const superAdminHub = computed(() => isSuperAdmin.value && branchContext.value.isSuperAdminHub);

const canUseBarista = computed(() => {
  return roles.value.includes('super admin') || roles.value.includes('admin') || roles.value.includes('barista');
});
const isBaristaOnly = computed(() => {
  return roles.value.includes('barista') && !roles.value.includes('admin') && !roles.value.includes('super admin');
});

const branches = computed(() => branchContext.value.branches || []);

function selectBranch(id) {
  router.post(route('branch-context.select', id));
}

function clearBranch() {
  router.post(route('branch-context.clear'));
}
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between w-full">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">لوحة التحكم</h2>
                <div v-if="isSuperAdmin && !superAdminHub && branchContext.activeBranchName" class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-600">
                        الفرع النشط: <strong class="text-gray-900">{{ branchContext.activeBranchName }}</strong>
                    </span>
                    <button
                        type="button"
                        class="text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded-lg transition"
                        @click="clearBranch"
                    >
                        العودة لعرض جميع الفروع
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-10">

                <!-- سوبر أدمن — العرض المركزي -->
                <template v-if="superAdminHub">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">إدارة مركزية (جميع الفروع)</h3>
                        <p class="text-sm text-gray-600 mb-6">
                            المنتجات والمواد الخام والمستخدمين وعرض الشاشة والمشتريات والريسبي والتقييمات مركزية.
                            تقارير المبيعات هنا تعرض إجمالياً لجميع الفروع. المصروفات في هذه الشاشة تعرض المصروفات المركزية فقط (بدون فرع).
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <a href="/products" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-green-500 text-4xl mb-4">📦</div>
                                    <h3 class="text-lg font-semibold text-gray-700">المنتجات</h3>
                                    <p class="text-sm text-gray-500">كتالوج موحّد لكل الفروع</p>
                                </div>
                            </a>
                            <a href="/raw-materials" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-amber-500 text-4xl mb-4">🏭</div>
                                    <h3 class="text-lg font-semibold text-gray-700">المواد الخام</h3>
                                    <p class="text-sm text-gray-500">مركزية لكل الفروع</p>
                                </div>
                            </a>
                            <a :href="route('admin.users.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-gray-600 text-4xl mb-4">👤</div>
                                    <h3 class="text-lg font-semibold text-gray-700">إدارة المستخدمين</h3>
                                    <p class="text-sm text-gray-500">تعيين كل مستخدم إلى فرع</p>
                                </div>
                            </a>
                            <a :href="route('admin.display-screen.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-cyan-500 text-4xl mb-4">🖥️</div>
                                    <h3 class="text-lg font-semibold text-gray-700">عرض الشاشة</h3>
                                    <p class="text-sm text-gray-500">محتوى مركزي</p>
                                </div>
                            </a>
                            <a href="/purchases" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-yellow-500 text-4xl mb-4">🛒</div>
                                    <h3 class="text-lg font-semibold text-gray-700">المشتريات</h3>
                                    <p class="text-sm text-gray-500">مركزية</p>
                                </div>
                            </a>
                            <a href="/expenses" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-purple-500 text-4xl mb-4">💸</div>
                                    <h3 class="text-lg font-semibold text-gray-700">المصروفات المركزية</h3>
                                    <p class="text-sm text-gray-500">مصروفات بدون فرع محدد</p>
                                </div>
                            </a>
                            <a
                                v-if="canManageFeedback"
                                href="/admin/feedback"
                                class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl"
                            >
                                <div class="flex flex-col items-center">
                                    <div class="text-yellow-500 text-4xl mb-4">⭐</div>
                                    <h3 class="text-lg font-semibold text-gray-700">التقييمات</h3>
                                    <p class="text-sm text-gray-500">مركزية</p>
                                </div>
                            </a>
                            <a v-if="canUseBarista" href="/barista" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                                <div class="flex flex-col items-center">
                                    <div class="text-indigo-500 text-4xl mb-4">🧑‍🍳</div>
                                    <h3 class="text-lg font-semibold text-gray-700">الريسبي</h3>
                                    <p class="text-sm text-gray-500">مركزي</p>
                                </div>
                            </a>
                            <a
                                v-if="canViewReports"
                                href="/sales-report"
                                class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl ring-2 ring-blue-100"
                            >
                                <div class="flex flex-col items-center">
                                    <div class="text-red-500 text-4xl mb-4">📊</div>
                                    <h3 class="text-lg font-semibold text-gray-700">تقارير المبيعات الإجمالية</h3>
                                    <p class="text-sm text-gray-500">جميع الفروع مع توزيع حسب الفرع</p>
                                </div>
                            </a>
                            <a :href="route('admin.branches.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl ring-2 ring-emerald-100">
                                <div class="flex flex-col items-center">
                                    <div class="text-emerald-600 text-4xl mb-4">➕</div>
                                    <h3 class="text-lg font-semibold text-gray-700">إدارة الفروع</h3>
                                    <p class="text-sm text-gray-500">إضافة أو تعديل الفروع</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">الدخول إلى فرع</h3>
                        <p class="text-sm text-gray-600 mb-6">
                            اختر فرعاً للوصول إلى الكاشير والفواتير والموظفين ومجموعات الحضور وتقارير المبيعات الخاصة بالفرع ومصروفات ذلك الفرع فقط (المصروفات المركزية من لوحة الفروع الموحدة).
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <button
                                v-for="b in branches"
                                :key="b.id"
                                type="button"
                                class="block p-6 bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl text-right border border-slate-200"
                                @click="selectBranch(b.id)"
                            >
                                <div class="flex flex-col items-center">
                                    <div class="text-slate-600 text-4xl mb-4">🏢</div>
                                    <h3 class="text-lg font-semibold text-gray-800">{{ b.name }}</h3>
                                    <p class="text-sm text-gray-500 mt-2">فتح لوحة الفرع</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- باقي الأدوار + سوبر أدمن داخل فرع -->
                <template v-else>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <a v-if="!isBaristaOnly" href="/cashier" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-blue-500 text-4xl mb-4">🏪</div>
                                <h3 class="text-lg font-semibold text-gray-700">الكاشير</h3>
                                <p class="text-sm text-gray-500">إدارة عمليات البيع بسهولة</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/products" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-green-500 text-4xl mb-4">📦</div>
                                <h3 class="text-lg font-semibold text-gray-700">المنتجات</h3>
                                <p class="text-sm text-gray-500">إدارة المنتجات وتحديثها</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly && canViewReports" href="/sales-report" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-red-500 text-4xl mb-4">📊</div>
                                <h3 class="text-lg font-semibold text-gray-700">تقارير المبيعات</h3>
                                <p class="text-sm text-gray-500">عرض تحليلات وتقارير المبيعات</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/expenses" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-purple-500 text-4xl mb-4">💸</div>
                                <h3 class="text-lg font-semibold text-gray-700">المصروفات</h3>
                                <p class="text-sm text-gray-500">مصروفات هذا الفرع فقط</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly && canManageAttendance" href="/employees" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-orange-500 text-4xl mb-4">👥</div>
                                <h3 class="text-lg font-semibold text-gray-700">الموظفين</h3>
                                <p class="text-sm text-gray-500">إدارة الحضور والانصراف</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/invoices" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-indigo-500 text-4xl mb-4">🧾</div>
                                <h3 class="text-lg font-semibold text-gray-700">الفواتير</h3>
                                <p class="text-sm text-gray-500">عرض فواتير اليوم الحالي</p>
                            </div>
                        </a>

                        <a
                            v-if="!isBaristaOnly && canManageFeedback"
                            href="/admin/feedback"
                            class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl"
                        >
                            <div class="flex flex-col items-center">
                                <div class="text-yellow-500 text-4xl mb-4">⭐</div>
                                <h3 class="text-lg font-semibold text-gray-700">التقييمات</h3>
                                <p class="text-sm text-gray-500">إدارة تقييمات العملاء</p>
                            </div>
                        </a>

                        <a v-if="canUseBarista" href="/barista" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl">
                            <div class="flex flex-col items-center">
                                <div class="text-indigo-500 text-4xl mb-4">🧑‍🍳</div>
                                <h3 class="text-lg font-semibold text-gray-700">الريسبي</h3>
                                <p class="text-sm text-gray-500">وصفات الباريستا حسب المنتج والمقاس</p>
                            </div>
                        </a>
                    </div>
                </template>

            </div>
        </div>
    </AppLayout>
</template>
