<?php

namespace App\Services;

use App\Support\PermissionCatalog;
use App\Support\TenantFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * مزامنة آمنة للصلاحيات:
 * - إنشاء الصلاحيات الجديدة (firstOrCreate)
 * - الإبقاء على الصلاحيات القديمة للتوافق
 * - منح الأدوار الأساسية الصلاحيات الافتراضية بالإضافة فقط (givePermissionTo)
 * - ترحيل الصلاحيات القديمة → الجديدة لأي دور مخصص
 */
class PermissionSyncService
{
    /**
     * @return array{created_permissions: int, roles_updated: int, grants_added: int}
     */
    public function sync(): array
    {
        $created = 0;
        $grantsAdded = 0;
        $rolesUpdated = 0;

        DB::transaction(function () use (&$created, &$grantsAdded, &$rolesUpdated) {
            foreach (PermissionCatalog::allPermissionNames() as $name) {
                $permission = Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web']
                );
                if ($permission->wasRecentlyCreated) {
                    $created++;
                }
            }

            // الإبقاء على الصلاحيات القديمة وعدم حذفها
            foreach (PermissionCatalog::legacyPermissionNames() as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web']
                );
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (PermissionCatalog::systemRoleDefaults() as $roleName => $permissions) {
                $role = Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => 'web']
                );

                $toGrant = $this->filterByAvailableFeatures($permissions);
                $added = $this->grantMissing($role, $toGrant);
                // منح الصلاحيات القديمة أيضاً للتوافق مع الكود الحالي
                $added += $this->grantLegacyEquivalents($role, $toGrant);

                if ($added > 0) {
                    $rolesUpdated++;
                    $grantsAdded += $added;
                }
            }

            // أدوار مخصصة: ترحيل من الصلاحيات القديمة فقط (إضافة)
            $systemNames = array_keys(PermissionCatalog::systemRoleDefaults());
            Role::query()
                ->whereNotIn('name', $systemNames)
                ->with('permissions')
                ->each(function (Role $role) use (&$grantsAdded, &$rolesUpdated) {
                    $fromLegacy = $this->expandFromLegacy($role->permissions->pluck('name')->all());
                    $added = $this->grantMissing($role, $fromLegacy);
                    if ($added > 0) {
                        $rolesUpdated++;
                        $grantsAdded += $added;
                    }
                });

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Log::info('permissions.sync', compact('created', 'rolesUpdated', 'grantsAdded'));

        return [
            'created_permissions' => $created,
            'roles_updated' => $rolesUpdated,
            'grants_added' => $grantsAdded,
        ];
    }

    /**
     * للمجموعات المرتبطة بميزة اختيارية: امنح فقط إن وُجد tenant مفعّل،
     * أو إن كانت الصلاحية ليست ضمن مجموعة feature.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function filterByAvailableFeatures(array $permissions): array
    {
        $featureByPermission = [];
        foreach (PermissionCatalog::groups() as $group) {
            $feature = $group['feature'] ?? null;
            foreach ($group['permissions'] as $permission) {
                $featureByPermission[$permission['name']] = $feature;
            }
        }

        return array_values(array_filter($permissions, function (string $name) use ($featureByPermission) {
            $feature = $featureByPermission[$name] ?? null;
            if ($feature === null) {
                return true;
            }

            // إن لم يكن أي tenant مفعّلاً بعد، ما زلنا نمنح لـ super admin فقط عبر defaults
            // لكن نمنح للجميع الافتراضيين إن وُجد tenant واحد على الأقل مفعّل،
            // وإلا نمنح أيضاً حتى لا يفقد SA الإعدادات — الصلاحية تُفحص وقت التشغيل مع العلم.
            return TenantFeatures::anyTenantHas($feature) || $feature === 'closing_photos';
        }));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grantMissing(Role $role, array $permissions): int
    {
        $existing = $role->permissions->pluck('name')->all();
        $missing = array_values(array_diff($permissions, $existing));

        if ($missing === []) {
            return 0;
        }

        $role->givePermissionTo($missing);
        $role->load('permissions');

        return count($missing);
    }

    /**
     * أبقِ الصلاحيات القديمة متزامنة مع الأدوار التي حصلت على المكافئ الجديد.
     *
     * @param  list<string>  $newPermissions
     */
    private function grantLegacyEquivalents(Role $role, array $newPermissions): int
    {
        $legacyToGrant = [];
        foreach (PermissionCatalog::legacyMap() as $legacy => $mapped) {
            if (count(array_intersect($mapped, $newPermissions)) > 0) {
                $legacyToGrant[] = $legacy;
            }
        }

        // أدوار النظام كانت تمتلك هذه الصلاحيات — تأكد من بقائها
        $defaults = PermissionCatalog::systemRoleDefaults()[$role->name] ?? [];
        if ($defaults !== []) {
            foreach (PermissionCatalog::legacyMap() as $legacy => $mapped) {
                if (count(array_intersect($mapped, $defaults)) > 0) {
                    $legacyToGrant[] = $legacy;
                }
            }
        }

        return $this->grantMissing($role, array_unique($legacyToGrant));
    }

    /**
     * @param  list<string>  $currentNames
     * @return list<string>
     */
    private function expandFromLegacy(array $currentNames): array
    {
        $expanded = [];
        foreach (PermissionCatalog::legacyMap() as $legacy => $mapped) {
            if (in_array($legacy, $currentNames, true)) {
                array_push($expanded, ...$mapped);
            }
        }

        return array_values(array_unique($expanded));
    }
}
