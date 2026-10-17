import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * مساعد الواجهة لفحص صلاحيات المستخدم الحالي.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth?.user?.permissions || []);
    const roles = computed(() => page.props.auth?.user?.roles || []);

    const can = (...names) => names.some((name) => permissions.value.includes(name));
    const hasRole = (...names) => names.some((name) => roles.value.includes(name));

    return { permissions, roles, can, hasRole };
}
