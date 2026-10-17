<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    teams: { type: Array, default: () => [] },
    permissionGroups: { type: Array, default: () => [] },
    systemRoleNames: { type: Array, default: () => [] },
});

const page = usePage();
const search = ref('');
const selectedId = ref(null);
const isCreating = ref(false);
const expandedGroups = ref({});
const showDeleteConfirm = ref(false);

const form = useForm({
    name: '',
    display_name: '',
    description: '',
    permissions: [],
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const filteredTeams = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.teams;
    return props.teams.filter((team) => {
        const hay = `${team.display_name} ${team.name} ${team.description || ''}`.toLowerCase();
        return hay.includes(q);
    });
});

const selectedTeam = computed(() => {
    if (isCreating.value || selectedId.value == null) return null;
    return props.teams.find((t) => t.id === selectedId.value) || null;
});

const activePermissionsCount = computed(() => form.permissions.length);

const totalPermissions = computed(() =>
    props.permissionGroups.reduce((sum, g) => sum + g.permissions.length, 0)
);

function ensureGroupsExpanded() {
    const next = { ...expandedGroups.value };
    props.permissionGroups.forEach((group) => {
        if (next[group.key] === undefined) {
            next[group.key] = true;
        }
    });
    expandedGroups.value = next;
}

function selectTeam(team) {
    isCreating.value = false;
    selectedId.value = team.id;
    form.clearErrors();
    form.name = team.name;
    form.display_name = team.display_name;
    form.description = team.description || '';
    form.permissions = [...team.permissions];
    ensureGroupsExpanded();
}

function startCreate() {
    isCreating.value = true;
    selectedId.value = null;
    form.clearErrors();
    form.reset();
    form.name = '';
    form.display_name = '';
    form.description = '';
    form.permissions = [];
    ensureGroupsExpanded();
}

function toggleGroup(key) {
    expandedGroups.value[key] = !expandedGroups.value[key];
}

function isChecked(permissionName) {
    return form.permissions.includes(permissionName);
}

function togglePermission(permissionName) {
    if (isChecked(permissionName)) {
        form.permissions = form.permissions.filter((p) => p !== permissionName);
    } else {
        form.permissions = [...form.permissions, permissionName];
    }
}

function groupSelectedCount(group) {
    return group.permissions.filter((p) => isChecked(p.name)).length;
}

function selectAllInGroup(group) {
    const names = group.permissions.map((p) => p.name);
    const allSelected = names.every((n) => isChecked(n));
    if (allSelected) {
        form.permissions = form.permissions.filter((p) => !names.includes(p));
    } else {
        form.permissions = Array.from(new Set([...form.permissions, ...names]));
    }
}

function selectAllPermissions() {
    const all = props.permissionGroups.flatMap((g) => g.permissions.map((p) => p.name));
    const allSelected = all.length > 0 && all.every((n) => isChecked(n));
    form.permissions = allSelected ? [] : [...all];
}

function saveTeam() {
    if (isCreating.value) {
        form.post(route('admin.roles.store'), {
            preserveScroll: true,
            onSuccess: () => {
                isCreating.value = false;
            },
        });
        return;
    }

    if (!selectedTeam.value) return;

    form.put(route('admin.roles.update', selectedTeam.value.id), {
        preserveScroll: true,
    });
}

function confirmDelete() {
    if (!selectedTeam.value || selectedTeam.value.is_system) return;
    showDeleteConfirm.value = true;
}

function deleteTeam() {
    if (!selectedTeam.value) return;
    router.delete(route('admin.roles.destroy', selectedTeam.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            selectedId.value = null;
            isCreating.value = false;
        },
    });
}

function scopeBadgeClass(scope) {
    if (scope === 'branch') return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    if (scope === 'central') return 'bg-violet-50 text-violet-700 border border-violet-100';
    return 'bg-slate-100 text-slate-600 border border-slate-200';
}

function initSelection() {
    ensureGroupsExpanded();
    const queryTeam = Number(new URLSearchParams(window.location.search).get('team') || 0);
    if (queryTeam && props.teams.some((t) => t.id === queryTeam)) {
        selectTeam(props.teams.find((t) => t.id === queryTeam));
        return;
    }
    if (props.teams.length > 0) {
        selectTeam(props.teams[0]);
    }
}

watch(
    () => props.teams,
    (teams) => {
        if (isCreating.value) return;
        if (selectedId.value && teams.some((t) => t.id === selectedId.value)) {
            const team = teams.find((t) => t.id === selectedId.value);
            form.permissions = [...team.permissions];
            form.display_name = team.display_name;
            form.description = team.description || '';
            form.name = team.name;
            return;
        }
        if (teams.length > 0 && !selectedId.value) {
            selectTeam(teams[0]);
        }
    },
    { deep: true }
);

initSelection();
</script>

<template>
    <AppLayout title="الصلاحيات والفرق">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">الصلاحيات والفرق</h2>
        </template>

        <div class="py-6 sm:py-8" dir="rtl">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    v-if="flashSuccess"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {{ flashError }}
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden min-h-[70vh] flex flex-col lg:flex-row">
                    <!-- Sidebar: الفرق -->
                    <aside class="w-full lg:w-80 xl:w-96 border-b lg:border-b-0 lg:border-l border-gray-200 bg-gray-50/60 flex flex-col shrink-0">
                        <div class="p-4 sm:p-5 border-b border-gray-200 bg-white">
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-base font-bold text-gray-900">الفرق</h3>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ teams.length }} فرق</p>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700 transition shadow-sm"
                                    @click="startCreate"
                                >
                                    <span class="text-lg leading-none">+</span>
                                    جديد
                                </button>
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z" />
                                    </svg>
                                </span>
                                <input
                                    v-model="search"
                                    type="search"
                                    placeholder="ابحث عن فريق..."
                                    class="w-full rounded-xl border-gray-200 bg-gray-50 pr-10 pl-3 py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-3 space-y-2 max-h-[40vh] lg:max-h-none">
                            <button
                                v-for="team in filteredTeams"
                                :key="team.id"
                                type="button"
                                class="w-full text-right rounded-xl px-3 py-3 transition border"
                                :class="
                                    !isCreating && selectedId === team.id
                                        ? 'bg-blue-600 border-blue-600 text-white shadow-md'
                                        : 'bg-white border-transparent hover:border-gray-200 hover:bg-white text-gray-800'
                                "
                                @click="selectTeam(team)"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                                        :class="
                                            !isCreating && selectedId === team.id
                                                ? 'bg-white/20 text-white'
                                                : 'bg-blue-50 text-blue-700'
                                        "
                                    >
                                        {{ team.initial }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold truncate">{{ team.display_name }}</div>
                                        <div
                                            class="text-xs mt-0.5 truncate"
                                            :class="!isCreating && selectedId === team.id ? 'text-blue-100' : 'text-gray-500'"
                                        >
                                            {{ team.permissions_count }} صلاحيات — {{ team.users_count }} مستخدمين
                                        </div>
                                    </div>
                                </div>
                            </button>

                            <div
                                v-if="filteredTeams.length === 0"
                                class="text-center text-sm text-gray-500 py-8"
                            >
                                لا توجد نتائج
                            </div>
                        </div>
                    </aside>

                    <!-- Main editor -->
                    <section class="flex-1 min-w-0 flex flex-col">
                        <div
                            v-if="!selectedTeam && !isCreating"
                            class="flex-1 flex items-center justify-center text-gray-500 p-10"
                        >
                            اختر فريقاً من القائمة أو أنشئ فريقاً جديداً
                        </div>

                        <template v-else>
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-gray-200 bg-white">
                                <div class="flex flex-wrap items-center gap-2 min-w-0">
                                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 truncate">
                                        {{ isCreating ? 'فريق جديد' : selectedTeam.display_name }}
                                    </h3>
                                    <span
                                        v-if="!isCreating"
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-5-3.87M9 20H4v-2a4 4 0 015-3.87m6-2.13a4 4 0 10-8 0 4 4 0 008 0zm6-4a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ selectedTeam.users_count }} مستخدمين
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        {{ activePermissionsCount }} صلاحيات مفعلة
                                    </span>
                                    <span
                                        v-if="selectedTeam?.is_system"
                                        class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 border border-amber-100"
                                    >
                                        دور أساسي
                                    </span>
                                </div>

                                <button
                                    v-if="selectedTeam && !selectedTeam.is_system"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 transition"
                                    @click="confirmDelete"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h8" />
                                    </svg>
                                    حذف
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-8">
                                <!-- بيانات الفريق -->
                                <div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">اسم الفريق</label>
                                            <input
                                                v-model="form.display_name"
                                                type="text"
                                                class="w-full rounded-xl border-gray-200 focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                placeholder="مثال: المحاسبين"
                                            >
                                            <p v-if="form.errors.display_name" class="mt-1 text-xs text-red-600">{{ form.errors.display_name }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">الوصف</label>
                                            <input
                                                v-model="form.description"
                                                type="text"
                                                class="w-full rounded-xl border-gray-200 focus:border-blue-500 focus:ring-blue-500 text-sm"
                                                placeholder="وصف مختصر للفريق"
                                            >
                                            <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                                        </div>
                                    </div>

                                    <div v-if="isCreating || (selectedTeam && !selectedTeam.is_system)" class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                            المعرّف التقني
                                            <span class="text-gray-400 font-normal">(بالإنجليزية)</span>
                                        </label>
                                        <input
                                            v-model="form.name"
                                            type="text"
                                            class="w-full md:w-1/2 rounded-xl border-gray-200 focus:border-blue-500 focus:ring-blue-500 text-sm font-mono"
                                            placeholder="accountants"
                                            :disabled="selectedTeam?.is_system"
                                        >
                                        <p class="mt-1 text-xs text-gray-500">يُستخدم داخلياً في النظام ولا يُعرض للمستخدمين.</p>
                                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                                    </div>
                                    <div v-else-if="selectedTeam?.is_system" class="mt-3 text-xs text-gray-500">
                                        المعرّف التقني:
                                        <code class="bg-gray-100 px-1.5 py-0.5 rounded text-gray-700">{{ selectedTeam.name }}</code>
                                        — لا يمكن تغييره للأدوار الأساسية
                                    </div>

                                    <div class="mt-4">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 transition shadow-sm"
                                            :disabled="form.processing"
                                            @click="saveTeam"
                                        >
                                            {{ form.processing ? 'جاري الحفظ...' : (isCreating ? 'إنشاء الفريق' : 'حفظ بيانات الفريق') }}
                                        </button>
                                    </div>
                                </div>

                                <!-- الصلاحيات -->
                                <div>
                                    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                                        <div>
                                            <h4 class="text-base font-bold text-gray-900">الصلاحيات</h4>
                                            <p class="text-sm text-gray-500 mt-1">
                                                فعّل الصلاحيات المطلوبة لهذا الفريق حسب المجموعة (عرض / إدارة منفصلان).
                                                الصلاحيات المقيّدة بفرع تُطبَّق تلقائياً على فرع المستخدم فقط.
                                                <span class="text-gray-400">({{ totalPermissions }} صلاحية متاحة لهذا الـ tenant)</span>
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-sm font-semibold text-blue-600 hover:text-blue-700"
                                            @click="selectAllPermissions"
                                        >
                                            تحديد الكل
                                        </button>
                                    </div>

                                    <div class="space-y-3">
                                        <div
                                            v-for="group in permissionGroups"
                                            :key="group.key"
                                            class="rounded-2xl border border-gray-200 overflow-hidden bg-white"
                                        >
                                            <div class="flex items-center justify-between gap-3 px-4 py-3 bg-gray-50 border-b border-gray-100">
                                                <button
                                                    type="button"
                                                    class="flex items-center gap-2 min-w-0 text-right flex-1"
                                                    @click="toggleGroup(group.key)"
                                                >
                                                    <svg
                                                        class="w-4 h-4 text-gray-400 transition-transform shrink-0"
                                                        :class="expandedGroups[group.key] ? 'rotate-180' : ''"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                    <span class="font-semibold text-gray-800">{{ group.label }}</span>
                                                    <span class="text-xs text-gray-500">
                                                        {{ groupSelectedCount(group) }}/{{ group.count }} صلاحيات
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="text-sm font-semibold text-blue-600 hover:text-blue-700 shrink-0"
                                                    @click="selectAllInGroup(group)"
                                                >
                                                    تحديد الكل
                                                </button>
                                            </div>

                                            <div v-show="expandedGroups[group.key]" class="divide-y divide-gray-100">
                                                <label
                                                    v-for="permission in group.permissions"
                                                    :key="permission.name"
                                                    class="flex items-start gap-3 px-4 py-3.5 cursor-pointer hover:bg-gray-50/80 transition"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                        :checked="isChecked(permission.name)"
                                                        @change="togglePermission(permission.name)"
                                                    >
                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex flex-wrap items-center gap-2">
                                                            <span class="text-sm font-medium text-gray-900">{{ permission.label }}</span>
                                                            <span
                                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                                :class="scopeBadgeClass(permission.scope)"
                                                            >
                                                                {{ permission.scope_label }}
                                                            </span>
                                                        </span>
                                                        <span v-if="permission.description" class="block text-xs text-gray-500 mt-0.5">
                                                            {{ permission.description }}
                                                        </span>
                                                        <span class="block text-xs text-gray-400 font-mono mt-0.5 text-left" dir="ltr">{{ permission.name }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div
                                            v-if="permissionGroups.length === 0"
                                            class="rounded-2xl border border-dashed border-gray-200 p-8 text-center text-sm text-gray-500"
                                        >
                                            لا توجد صلاحيات معرفة بعد. سيتم إضافة كتالوج الصلاحيات في الخطوة التالية.
                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50 transition shadow-sm"
                                            :disabled="form.processing"
                                            @click="saveTeam"
                                        >
                                            {{ form.processing ? 'جاري الحفظ...' : 'حفظ الصلاحيات' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </div>

        <!-- Delete confirm -->
        <div
            v-if="showDeleteConfirm"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            @click.self="showDeleteConfirm = false"
        >
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" dir="rtl">
                <h3 class="text-lg font-bold text-gray-900 mb-2">حذف الفريق</h3>
                <p class="text-sm text-gray-600 mb-6">
                    هل أنت متأكد من حذف «{{ selectedTeam?.display_name }}»؟ لا يمكن التراجع عن هذا الإجراء.
                </p>
                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100"
                        @click="showDeleteConfirm = false"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                        @click="deleteTeam"
                    >
                        تأكيد الحذف
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
