<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    suppliers: { type: Array, default: () => [] },
    search: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    canViewPayments: { type: Boolean, default: false },
});

const page = usePage();
const flash = computed(() => page.props.flash || {});
const canAdd = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return props.canManage
        || permissions.includes('suppliers.manage')
        || roles.includes('super admin');
});
const showRemaining = computed(() => {
    const permissions = page.props.auth?.user?.permissions || [];
    const roles = page.props.auth?.user?.roles || [];
    return props.canViewPayments
        || permissions.includes('suppliers.payments.view')
        || permissions.includes('suppliers.payments.manage')
        || roles.includes('super admin');
});

function money(value) {
    return Number(value || 0).toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function applySearch(event) {
    router.get(route('admin.suppliers.index'), { search: event.target.search.value }, {
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <AppLayout title="الموردين">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">الموردين</h2>
        </template>

        <div class="py-6 sm:py-10" dir="rtl">
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6">
                <div v-if="flash.success" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ flash.success }}
                </div>
                <div v-if="flash.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ flash.error }}
                </div>

                <div class="space-y-3">
                    <Link
                        v-if="canAdd"
                        :href="route('admin.suppliers.create')"
                        class="inline-flex items-center justify-center rounded-lg bg-green-600 px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-green-700"
                        style="background-color: #16a34a; color: #ffffff;"
                    >
                        إضافة مورد
                    </Link>
                    <form class="flex gap-2" @submit.prevent="applySearch">
                        <input
                            name="search"
                            :value="search"
                            type="search"
                            placeholder="بحث بالاسم أو الهاتف أو المسؤول"
                            class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-3 py-2.5"
                        />
                        <button type="submit" class="shrink-0 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white">
                            بحث
                        </button>
                    </form>
                </div>

                <div v-if="!suppliers.length" class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-gray-500">
                    لا يوجد موردون مطابقون.
                </div>

                <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <Link
                        v-for="supplier in suppliers"
                        :key="supplier.id"
                        :href="route('admin.suppliers.show', supplier.id)"
                        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-teal-300 hover:shadow"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-bold text-gray-900">{{ supplier.name }}</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ supplier.contact_person || 'بدون مسؤول' }}
                                    <span v-if="supplier.phone"> · {{ supplier.phone }}</span>
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="supplier.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ supplier.is_active ? 'نشط' : 'متوقف' }}
                            </span>
                        </div>
                        <div class="mt-4 grid grid-cols-1 gap-2 text-sm" :class="showRemaining ? 'sm:grid-cols-2' : ''">
                            <div class="rounded-xl bg-gray-50 px-3 py-2">
                                <div class="text-gray-600">إجمالي الفواتير</div>
                                <div class="font-bold text-gray-900">{{ money(supplier.purchases_total) }}</div>
                            </div>
                            <div v-if="showRemaining" class="rounded-xl px-3 py-2" style="background-color:#fef3c7">
                                <div class="font-semibold text-amber-900">المتبقي</div>
                                <div class="text-lg font-bold text-amber-950">{{ money(supplier.remaining) }}</div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
