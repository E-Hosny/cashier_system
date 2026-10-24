<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

defineProps({
    title: String,
});

const showingSidebar = ref(false);
const page = usePage();
const branchContext = computed(() => page.props.branchContext || {});
const hideBranchScopedNav = computed(() => branchContext.value?.isSuperAdminHub === true);
const userRoles = computed(() => page.props.auth?.user?.roles || []);
const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const tenantFeatures = computed(() => page.props.tenantFeatures || {});
const isSuperAdmin = computed(() => userRoles.value.includes('super admin'));
const isHrOnly = computed(() =>
    userRoles.value.includes('hr') &&
    !userRoles.value.includes('admin') &&
    !userRoles.value.includes('super admin') &&
    !userRoles.value.includes('cashier')
);
const tenantBranding = computed(() => page.props.tenantBranding || { name: null, logoUrl: null });
const isBaristaOnly = computed(() =>
    userRoles.value.includes('barista') &&
    !userRoles.value.includes('admin') &&
    !userRoles.value.includes('super admin')
);
const hasPermission = (...names) => names.some((name) => userPermissions.value.includes(name));
const hideOperationalNav = computed(() => isBaristaOnly.value || isHrOnly.value);
const canManageAttendance = computed(() =>
    hasPermission('employees.attendance', 'employees.view', 'manage employee attendance')
);
const canViewProducts = computed(() =>
    hasPermission('products.view', 'manage products') ||
    userRoles.value.includes('admin') ||
    userRoles.value.includes('cashier') ||
    isSuperAdmin.value
);
const showSuppliersNav = computed(() =>
    hasPermission('suppliers.view', 'suppliers.manage')
);
const showPurchasesNav = computed(
    () => !hideOperationalNav.value
        && branchContext.value.activeBranchId == null
        && (hasPermission('purchases.view', 'purchases.manage') || isSuperAdmin.value || userRoles.value.includes('admin') || userRoles.value.includes('cashier'))
);
const showRawMaterialsNav = computed(
    () => !isBaristaOnly.value
        && !isHrOnly.value
        && branchContext.value.isSuperAdminHub === true
        && (hasPermission('inventory.view', 'inventory.manage') || isSuperAdmin.value)
);
const canUseBarista = computed(() =>
    hasPermission('barista.use') ||
    userRoles.value.includes('barista') ||
    userRoles.value.includes('admin') ||
    userRoles.value.includes('super admin')
);
const canManageJobApplications = computed(() =>
    hasPermission('job_applications.view', 'job_applications.manage') ||
    userRoles.value.includes('admin') ||
    userRoles.value.includes('super admin')
);
const showEmployeesNav = computed(() =>
    canManageAttendance.value && !isBaristaOnly.value && (
        isHrOnly.value || isSuperAdmin.value || !hideBranchScopedNav.value
    )
);
const employeesNavHref = computed(() =>
    (isHrOnly.value || (isSuperAdmin.value && hideBranchScopedNav.value))
        ? route('admin.employees.index', { view: 'all' })
        : route('admin.employees.index')
);
const showClosingPhotosBrowseNav = computed(() =>
    !!tenantFeatures.value.closing_photos && (
        hasPermission('closing_photos.browse') ||
        isHrOnly.value ||
        isSuperAdmin.value ||
        userRoles.value.includes('admin')
    )
);
const canUseCashier = computed(() =>
    hasPermission('cashier.use', 'use cashier') ||
    userRoles.value.includes('admin') ||
    userRoles.value.includes('cashier') ||
    isSuperAdmin.value
);
const canViewExpenses = computed(() =>
    hasPermission('expenses.view', 'expenses.manage') ||
    userRoles.value.includes('admin') ||
    userRoles.value.includes('cashier') ||
    isSuperAdmin.value
);
const canManageRoles = computed(() =>
    hasPermission('roles.manage') || isSuperAdmin.value
);
const canManageUsers = computed(() =>
    hasPermission('users.manage', 'manage users') || isSuperAdmin.value
);
const canManageOffers = computed(() =>
    hasPermission('offers.view', 'offers.manage') || isSuperAdmin.value
);
const canManageFeedback = computed(() =>
    hasPermission('feedback.view', 'feedback.manage') || userRoles.value.includes('admin') || isSuperAdmin.value
);
const canManageDisplay = computed(() =>
    hasPermission('display.manage') || isSuperAdmin.value
);
const canManageAttendanceGroups = computed(() =>
    (hasPermission('employees.attendance_groups') || isSuperAdmin.value) && !hideBranchScopedNav.value
);
const canManageTenantSettings = computed(() =>
    hasPermission('tenant.settings') || isSuperAdmin.value
);
const canManageClosingSettings = computed(() =>
    hasPermission('closing_photos.settings') || isSuperAdmin.value
);

const navSections = computed(() => {
    const sections = [];

    const main = [];
    if (!isBaristaOnly.value) {
        main.push({
            label: 'لوحة التحكم',
            href: route('dashboard'),
            active: route().current('dashboard'),
            icon: 'home',
        });
    }
    if (main.length) {
        sections.push({ key: 'main', title: null, items: main });
    }

    const operations = [];
    if (canUseCashier.value && !hideBranchScopedNav.value) {
        operations.push({
            label: 'الكاشير',
            href: route('cashier.index'),
            active: route().current('cashier.index') || route().current('invoices.*'),
            icon: 'cashier',
        });
    }
    if (canViewExpenses.value && !hideOperationalNav.value) {
        operations.push({
            label: 'المصروفات',
            href: route('expenses.index'),
            active: route().current('expenses.*'),
            icon: 'expense',
        });
    }
    if (showPurchasesNav.value) {
        operations.push({
            label: 'المشتريات',
            href: route('purchases.index'),
            active: route().current('purchases.*'),
            icon: 'purchase',
        });
    }
    if (showSuppliersNav.value) {
        operations.push({
            label: 'الموردين',
            href: route('admin.suppliers.index'),
            active: route().current('admin.suppliers.*'),
            icon: 'supplier',
        });
    }
    if (canUseBarista.value) {
        operations.push({
            label: 'الريسبي',
            href: route('barista.index'),
            active: route().current('barista.*'),
            icon: 'barista',
        });
    }
    if (operations.length) {
        sections.push({ key: 'operations', title: 'العمليات', items: operations });
    }

    const catalog = [];
    if (canViewProducts.value && !hideOperationalNav.value) {
        catalog.push({
            label: 'المنتجات النهائية',
            href: route('admin.products.index'),
            active: route().current('admin.products.*') || route().current('admin.categories.*'),
            icon: 'product',
        });
    }
    if (canManageOffers.value) {
        catalog.push({
            label: 'العروض',
            href: route('admin.offers.index'),
            active: route().current('admin.offers.*'),
            icon: 'offer',
        });
    }
    if (showRawMaterialsNav.value) {
        catalog.push({
            label: 'المواد الخام',
            href: route('admin.raw-materials.index'),
            active: route().current('admin.raw-materials.*') || route().current('admin.fridge.*'),
            icon: 'inventory',
        });
    }
    if (catalog.length) {
        sections.push({ key: 'catalog', title: 'المنتجات والمخزون', items: catalog });
    }

    const people = [];
    if (showEmployeesNav.value) {
        people.push({
            label: 'الموظفين',
            href: employeesNavHref.value,
            active: route().current('admin.employees.*')
                && !route().current('admin.employees.attendance-groups.*')
                && !route().current('admin.employees.attendance-settings.*'),
            icon: 'users',
        });
    }
    if (canManageAttendanceGroups.value) {
        people.push({
            label: 'مجموعات الحضور',
            href: route('admin.employees.attendance-groups.index'),
            active: route().current('admin.employees.attendance-groups.*'),
            icon: 'group',
        });
    }
    if (showClosingPhotosBrowseNav.value) {
        people.push({
            label: 'صور التقفيلة',
            href: route('admin.closing-photo-reports.browse'),
            active: route().current('admin.closing-photo-reports.browse'),
            icon: 'camera',
        });
    }
    if (canManageFeedback.value) {
        people.push({
            label: 'التقييمات',
            href: route('admin.feedback.index'),
            active: route().current('admin.feedback.*'),
            icon: 'star',
        });
    }
    if (canManageJobApplications.value) {
        people.push({
            label: 'طلبات التوظيف',
            href: route('admin.job-applications.index'),
            active: route().current('admin.job-applications.*'),
            icon: 'briefcase',
        });
    }
    if (people.length) {
        sections.push({ key: 'people', title: 'الموارد البشرية', items: people });
    }

    const admin = [];
    if (canManageUsers.value) {
        admin.push({
            label: 'إدارة المستخدمين',
            href: route('admin.users.index'),
            active: route().current('admin.users.*'),
            icon: 'user-cog',
        });
    }
    if (canManageRoles.value) {
        admin.push({
            label: 'الصلاحيات والفرق',
            href: route('admin.roles.index'),
            active: route().current('admin.roles.*'),
            icon: 'shield',
        });
    }
    if (canManageDisplay.value) {
        admin.push({
            label: 'عرض الشاشة',
            href: route('admin.display-screen.index'),
            active: route().current('admin.display-screen.*'),
            icon: 'screen',
        });
    }
    if (canManageTenantSettings.value) {
        admin.push({
            label: 'إعدادات البراند',
            href: route('admin.tenant-settings.index'),
            active: route().current('admin.tenant-settings.*'),
            icon: 'settings',
        });
    }
    if (canManageClosingSettings.value) {
        admin.push({
            label: 'إعدادات صور التقفيلة',
            href: route('admin.closing-photo-reports.settings'),
            active: route().current('admin.closing-photo-reports.settings'),
            icon: 'sliders',
        });
    }
    if (isSuperAdmin.value) {
        admin.push({
            label: 'اختبار Spaces',
            href: route('admin.spaces-lab.index'),
            active: route().current('admin.spaces-lab.*'),
            icon: 'lab',
        });
    }
    if (admin.length) {
        sections.push({ key: 'admin', title: 'الإدارة', items: admin });
    }

    return sections;
});

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const closeSidebar = () => {
    showingSidebar.value = false;
};
</script>

<template>
    <div>
        <Head :title="title" />

        <Banner />

        <div class="min-h-screen bg-gray-100" dir="rtl">
            <!-- Desktop sidebar -->
            <aside class="hidden lg:flex fixed inset-y-0 right-0 z-30 w-64 flex-col border-l border-gray-200 bg-white">
                <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-5">
                    <Link :href="route('dashboard')" class="flex min-w-0 items-center gap-2">
                        <img
                            v-if="tenantBranding.logoUrl"
                            :src="tenantBranding.logoUrl"
                            :alt="tenantBranding.name || 'الشعار'"
                            class="h-9 w-auto max-w-[130px] object-contain"
                        >
                        <span v-else class="truncate text-base font-bold text-gray-900">نظام الكاشير</span>
                    </Link>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    <div v-for="section in navSections" :key="section.key" class="mb-5 last:mb-0">
                        <div
                            v-if="section.title"
                            class="mb-2 px-3 text-[11px] font-semibold tracking-wide text-gray-400"
                        >
                            {{ section.title }}
                        </div>
                        <ul class="space-y-1">
                            <li v-for="item in section.items" :key="item.href + item.label">
                                <Link
                                    :href="item.href"
                                    class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                    :class="item.active
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'"
                                >
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs"
                                        :class="item.active ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 group-hover:bg-gray-200'"
                                    >
                                        <svg v-if="item.icon === 'home'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-4 0h4" /></svg>
                                        <svg v-else-if="item.icon === 'cashier'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h.01M11 15h2M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z" /></svg>
                                        <svg v-else-if="item.icon === 'expense'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v-1M5 12a7 7 0 1114 0 7 7 0 01-14 0z" /></svg>
                                        <svg v-else-if="item.icon === 'purchase'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m12-9l2 9M10 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" /></svg>
                                        <svg v-else-if="item.icon === 'supplier'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17h6m-7 4h8a2 2 0 002-2v-5H4v5a2 2 0 002 2zM3 10h18l-2-5H5L3 10z" /></svg>
                                        <svg v-else-if="item.icon === 'barista'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h8v6a4 4 0 01-8 0V7zm8 2h2a2 2 0 010 4h-2M6 19h12" /></svg>
                                        <svg v-else-if="item.icon === 'product'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        <svg v-else-if="item.icon === 'offer'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M4 4h6l10 10-6 6L4 10V4z" /></svg>
                                        <svg v-else-if="item.icon === 'inventory'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7h16M4 12h16M4 17h10" /></svg>
                                        <svg v-else-if="item.icon === 'users'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m6-4a4 4 0 11-8 0 4 4 0 018 0zm6 0a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else-if="item.icon === 'group'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        <svg v-else-if="item.icon === 'camera'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8h4l2-3h6l2 3h4v11H3V8z" /><circle cx="12" cy="13" r="3.5" stroke-width="2" /></svg>
                                        <svg v-else-if="item.icon === 'star'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9L12 3z" /></svg>
                                        <svg v-else-if="item.icon === 'briefcase'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V6a2 2 0 012-2h4a2 2 0 012 2v1m-10 0h12a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V9a2 2 0 012-2z" /></svg>
                                        <svg v-else-if="item.icon === 'user-cog'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 13a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM4 19a6.5 6.5 0 0113 0M19 11v2m0 4v.01M17.5 12.5l1.4 1.4m0-2.8l-1.4 1.4" /></svg>
                                        <svg v-else-if="item.icon === 'shield'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z" /></svg>
                                        <svg v-else-if="item.icon === 'screen'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5h16v11H4V5zm4 14h8" /></svg>
                                        <svg v-else-if="item.icon === 'settings'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.05 2.93l.2-1.43h1.5l.2 1.43a7.97 7.97 0 012.2.91l1.25-.86 1.06 1.06-.86 1.25c.38.68.67 1.42.91 2.2l1.43.2v1.5l-1.43.2a7.97 7.97 0 01-.91 2.2l.86 1.25-1.06 1.06-1.25-.86a7.97 7.97 0 01-2.2.91l-.2 1.43h-1.5l-.2-1.43a7.97 7.97 0 01-2.2-.91l-1.25.86-1.06-1.06.86-1.25a7.97 7.97 0 01-.91-2.2l-1.43-.2v-1.5l1.43-.2c.24-.78.53-1.52.91-2.2l-.86-1.25L6.4 2.98l1.25.86c.68-.38 1.42-.67 2.2-.91z" /><circle cx="12" cy="12" r="3" stroke-width="2" /></svg>
                                        <svg v-else-if="item.icon === 'sliders'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h8M16 18h4M14 4v4M6 10v4M14 16v4" /></svg>
                                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.1v2.3m0 13.2v2.3M3.1 9.75h2.3m13.2 0h2.3M5.6 5.6l1.6 1.6m9.6 9.6l1.6 1.6m0-12.8l-1.6 1.6m-9.6 9.6l-1.6 1.6M12 8.5a3.5 3.5 0 100 7 3.5 3.5 0 000-7z" /></svg>
                                    </span>
                                    <span class="truncate">{{ item.label }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </nav>

                <div class="border-t border-gray-100 p-4">
                    <div class="flex items-center gap-3 rounded-xl bg-gray-50 px-3 py-2.5">
                        <div
                            v-if="$page.props.jetstream.managesProfilePhotos"
                            class="h-9 w-9 shrink-0 overflow-hidden rounded-full"
                        >
                            <img
                                class="h-full w-full object-cover"
                                :src="$page.props.auth.user.profile_photo_url"
                                :alt="$page.props.auth.user.name"
                            >
                        </div>
                        <div
                            v-else
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700"
                        >
                            {{ ($page.props.auth.user.name || '?').charAt(0) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-gray-900">{{ $page.props.auth.user.name }}</div>
                            <div class="truncate text-xs text-gray-500">{{ $page.props.auth.user.email }}</div>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main column -->
            <div class="lg:pr-64">
                <!-- Top bar -->
                <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/95 backdrop-blur">
                    <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 lg:hidden"
                                @click="showingSidebar = true"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                </svg>
                            </button>
                            <div class="min-w-0 lg:hidden">
                                <Link :href="route('dashboard')" class="flex items-center gap-2">
                                    <img
                                        v-if="tenantBranding.logoUrl"
                                        :src="tenantBranding.logoUrl"
                                        :alt="tenantBranding.name || 'الشعار'"
                                        class="h-8 w-auto max-w-[110px] object-contain"
                                    >
                                    <span v-else class="text-sm font-bold text-gray-900">نظام الكاشير</span>
                                </Link>
                            </div>
                            <div v-if="$slots.header" class="hidden min-w-0 lg:block">
                                <slot name="header" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <div v-if="$page.props.jetstream.hasTeamFeatures" class="hidden sm:block">
                                <Dropdown align="left" width="60">
                                    <template #trigger>
                                        <button type="button" class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">
                                            {{ $page.props.auth.user.current_team.name }}
                                            <svg class="ms-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="w-60">
                                            <div class="block px-4 py-2 text-xs text-gray-400">Manage Team</div>
                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">Team Settings</DropdownLink>
                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">Create New Team</DropdownLink>
                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                <div class="border-t border-gray-200" />
                                                <div class="block px-4 py-2 text-xs text-gray-400">Switch Teams</div>
                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                    <form @submit.prevent="switchToTeam(team)">
                                                        <DropdownLink as="button">
                                                            <div class="flex items-center">
                                                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>
                                                                <div>{{ team.name }}</div>
                                                            </div>
                                                        </DropdownLink>
                                                    </form>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <Dropdown align="left" width="48">
                                <template #trigger>
                                    <button
                                        v-if="$page.props.jetstream.managesProfilePhotos"
                                        type="button"
                                        class="flex rounded-full border-2 border-transparent text-sm transition focus:border-gray-300 focus:outline-none"
                                    >
                                        <img class="size-8 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                                    >
                                        {{ $page.props.auth.user.name }}
                                        <svg class="ms-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <div class="block px-4 py-2 text-xs text-gray-400">الحساب</div>
                                    <DropdownLink :href="route('profile.show')">الملف الشخصي</DropdownLink>
                                    <DropdownLink v-if="canManageTenantSettings" :href="route('admin.tenant-settings.index')">الإعدادات</DropdownLink>
                                    <DropdownLink v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')">API Tokens</DropdownLink>
                                    <div class="border-t border-gray-200" />
                                    <form @submit.prevent="logout">
                                        <DropdownLink as="button">تسجيل الخروج</DropdownLink>
                                    </form>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <div v-if="$slots.header" class="border-t border-gray-100 px-4 py-4 sm:px-6 lg:hidden">
                        <slot name="header" />
                    </div>
                </header>

                <main>
                    <slot />
                </main>
            </div>
        </div>

        <!-- Mobile sidebar drawer -->
        <teleport to="body">
            <transition name="fade">
                <div
                    v-if="showingSidebar"
                    class="fixed inset-0 z-40 bg-black/40 lg:hidden"
                    @click="closeSidebar"
                />
            </transition>

            <transition name="slide-in-right">
                <aside
                    v-if="showingSidebar"
                    class="fixed inset-y-0 right-0 z-50 flex w-72 flex-col bg-white shadow-xl lg:hidden"
                    dir="rtl"
                >
                    <div class="flex h-16 items-center justify-between border-b border-gray-100 px-4">
                        <Link :href="route('dashboard')" class="flex items-center gap-2" @click="closeSidebar">
                            <img
                                v-if="tenantBranding.logoUrl"
                                :src="tenantBranding.logoUrl"
                                :alt="tenantBranding.name || 'الشعار'"
                                class="h-8 w-auto max-w-[120px] object-contain"
                            >
                            <span v-else class="text-base font-bold text-gray-900">نظام الكاشير</span>
                        </Link>
                        <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" @click="closeSidebar">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <nav class="flex-1 overflow-y-auto px-3 py-4">
                        <div v-for="section in navSections" :key="'m-' + section.key" class="mb-5 last:mb-0">
                            <div
                                v-if="section.title"
                                class="mb-2 px-3 text-[11px] font-semibold tracking-wide text-gray-400"
                            >
                                {{ section.title }}
                            </div>
                            <ul class="space-y-1">
                                <li v-for="item in section.items" :key="'m-' + item.href + item.label">
                                    <Link
                                        :href="item.href"
                                        class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                        :class="item.active
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-700 hover:bg-gray-50'"
                                        @click="closeSidebar"
                                    >
                                        <span class="truncate">{{ item.label }}</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </nav>

                    <div class="border-t border-gray-100 p-3 space-y-1">
                        <Link
                            :href="route('profile.show')"
                            class="block rounded-xl px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="closeSidebar"
                        >
                            الملف الشخصي
                        </Link>
                        <button
                            type="button"
                            class="block w-full rounded-xl px-3 py-2.5 text-right text-sm font-medium text-red-600 hover:bg-red-50"
                            @click="logout"
                        >
                            تسجيل الخروج
                        </button>
                    </div>
                </aside>
            </transition>
        </teleport>
    </div>
</template>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-in-right-enter-active,
.slide-in-right-leave-active {
    transition: transform 0.25s ease;
}
.slide-in-right-enter-from,
.slide-in-right-leave-to {
    transform: translateX(100%);
}
</style>
