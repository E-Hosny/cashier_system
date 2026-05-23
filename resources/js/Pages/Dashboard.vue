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
                <div v-if="isSuperAdmin && !superAdminHub && branchContext.activeBranchName" class="flex flex-wrap items-center gap-2 min-w-0">
                    <span class="text-sm text-gray-600 break-words min-w-0">
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

        <div class="py-6 sm:py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10 min-w-0">

                <!-- سوبر أدمن — العرض المركزي -->
                <template v-if="superAdminHub">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">إدارة مركزية (جميع الفروع)</h3>
                        <p class="text-sm text-gray-600 mb-6 leading-relaxed break-words">
                            المنتجات والمواد الخام والمستخدمين وعرض الشاشة والمشتريات والريسبي والتقييمات مركزية.
                            تقارير المبيعات هنا تعرض إجمالياً لجميع الفروع. المصروفات في هذه الشاشة تعرض المصروفات المركزية فقط (بدون فرع).
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 min-w-0">
                            <a href="/products" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-green-500 text-4xl mb-4">📦</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المنتجات</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">كتالوج موحّد لكل الفروع</p>
                                </div>
                            </a>
                            <a href="/raw-materials" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-amber-500 text-4xl mb-4">🏭</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المواد الخام</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">مركزية لكل الفروع</p>
                                </div>
                            </a>
                            <a :href="route('admin.users.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-gray-600 text-4xl mb-4">👤</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">إدارة المستخدمين</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">تعيين كل مستخدم إلى فرع</p>
                                </div>
                            </a>
                            <a :href="route('admin.display-screen.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-cyan-500 text-4xl mb-4">🖥️</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">عرض الشاشة</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">محتوى مركزي</p>
                                </div>
                            </a>
                            <a href="/purchases" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-yellow-500 text-4xl mb-4">🛒</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المشتريات</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">مركزية</p>
                                </div>
                            </a>
                            <a href="/expenses" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-purple-500 text-4xl mb-4">💸</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المصروفات المركزية</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">مصروفات بدون فرع محدد</p>
                                </div>
                            </a>
                            <a
                                v-if="canManageFeedback"
                                href="/admin/feedback"
                                class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0"
                            >
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-yellow-500 text-4xl mb-4">⭐</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">التقييمات</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">مركزية</p>
                                </div>
                            </a>
                            <a v-if="canUseBarista" href="/barista" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-indigo-500 text-4xl mb-4">🧑‍🍳</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">الريسبي</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">مركزي</p>
                                </div>
                            </a>
                            <a
                                v-if="canViewReports"
                                href="/sales-report"
                                class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl ring-2 ring-blue-100 min-w-0"
                            >
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-red-500 text-4xl mb-4">📊</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">تقارير المبيعات الإجمالية</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">جميع الفروع مع توزيع حسب الفرع</p>
                                </div>
                            </a>
                            <a :href="route('admin.branches.index')" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl ring-2 ring-emerald-100 min-w-0">
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-emerald-600 text-4xl mb-4">➕</div>
                                    <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">إدارة الفروع</h3>
                                    <p class="text-sm text-gray-500 break-words leading-relaxed">إضافة أو تعديل الفروع</p>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">الدخول إلى فرع</h3>
                        <p class="text-sm text-gray-600 mb-6 leading-relaxed break-words">
                            اختر فرعاً للوصول إلى الكاشير والفواتير والموظفين ومجموعات الحضور وتقارير المبيعات الخاصة بالفرع ومصروفات ذلك الفرع فقط (المصروفات المركزية من لوحة الفروع الموحدة).
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 min-w-0">
                            <button
                                v-for="b in branches"
                                :key="b.id"
                                type="button"
                                class="block p-6 bg-gradient-to-br from-slate-50 to-slate-100 rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl text-right border border-slate-200 min-w-0"
                                @click="selectBranch(b.id)"
                            >
                                <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                    <div class="text-slate-600 text-4xl mb-4">🏢</div>
                                    <h3 class="text-lg font-semibold text-gray-800 break-words leading-snug">{{ b.name }}</h3>
                                    <p class="text-sm text-gray-500 mt-2 break-words leading-relaxed">فتح لوحة الفرع</p>
                                </div>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- باقي الأدوار + سوبر أدمن داخل فرع -->
                <template v-else>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 min-w-0">
                        <a
                            v-if="!isBaristaOnly"
                            :href="route('admin.raw-materials.branch-pull')"
                            class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl ring-2 ring-amber-100 min-w-0"
                        >
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-amber-600 text-4xl mb-4">📥</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">سحب المواد الخام</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">استلام بالباركود لمخزون الفرع</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/cashier" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-blue-500 text-4xl mb-4">🏪</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">الكاشير</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">إدارة عمليات البيع بسهولة</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/products" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-green-500 text-4xl mb-4">📦</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المنتجات</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">إدارة المنتجات وتحديثها</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly && canViewReports" href="/sales-report" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-red-500 text-4xl mb-4">📊</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">تقارير المبيعات</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">عرض تحليلات وتقارير المبيعات</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/expenses" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-purple-500 text-4xl mb-4">💸</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">المصروفات</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">مصروفات هذا الفرع فقط</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly && canManageAttendance" href="/employees" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-orange-500 text-4xl mb-4">👥</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">الموظفين</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">إدارة الحضور والانصراف</p>
                            </div>
                        </a>

                        <a v-if="!isBaristaOnly" href="/invoices" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-indigo-500 text-4xl mb-4">🧾</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">الفواتير</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">عرض فواتير اليوم الحالي</p>
                            </div>
                        </a>

                        <a
                            v-if="!isBaristaOnly && canManageFeedback"
                            href="/admin/feedback"
                            class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0"
                        >
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-yellow-500 text-4xl mb-4">⭐</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">التقييمات</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">إدارة تقييمات العملاء</p>
                            </div>
                        </a>

                        <a v-if="canUseBarista" href="/barista" class="block p-6 bg-white rounded-lg shadow-lg transform transition hover:scale-105 hover:shadow-xl min-w-0">
                            <div class="flex flex-col items-stretch w-full text-center min-w-0">
                                <div class="text-indigo-500 text-4xl mb-4">🧑‍🍳</div>
                                <h3 class="text-lg font-semibold text-gray-700 break-words leading-snug">الريسبي</h3>
                                <p class="text-sm text-gray-500 break-words leading-relaxed">وصفات الباريستا حسب المنتج والمقاس</p>
                            </div>
                        </a>
                    </div>
                </template>

            </div>
        </div>
    </AppLayout>
</template>
