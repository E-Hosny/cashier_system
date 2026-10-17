<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /** الأدوار الأساسية — لا تُحذف ولا يُغيَّر اسمها التقني */
    public const SYSTEM_ROLES = ['admin', 'cashier', 'super admin', 'barista', 'hr'];

    public function index()
    {
        $user = Auth::user();
        $tenant = Tenant::query()->find($user->tenant_id);
        $features = TenantFeatures::for($tenant);
        $visibleNames = PermissionCatalog::permissionNamesForFeatures($features);

        $userCounts = $this->tenantUserCountsByRole($user->tenant_id);

        $roles = Role::query()
            ->with('permissions:id,name')
            ->orderByRaw("CASE name
                WHEN 'super admin' THEN 1
                WHEN 'admin' THEN 2
                WHEN 'cashier' THEN 3
                WHEN 'barista' THEN 4
                WHEN 'hr' THEN 5
                ELSE 100
            END")
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->transformRole(
                $role,
                (int) ($userCounts[$role->id] ?? 0),
                $visibleNames
            ));

        return Inertia::render('Admin/Roles/Index', [
            'teams' => $roles,
            'permissionGroups' => PermissionCatalog::groupsForFeatures($features),
            'systemRoleNames' => self::SYSTEM_ROLES,
            'tenantFeatures' => $features,
        ]);
    }

    public function store(Request $request)
    {
        $features = $this->currentTenantFeatures();
        $visibleNames = PermissionCatalog::permissionNamesForFeatures($features);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-\s]+$/i', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($visibleNames)],
        ]);

        $role = Role::create([
            'name' => trim($validated['name']),
            'guard_name' => 'web',
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->syncPermissions($this->withLegacyPermissions($validated['permissions'] ?? []));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index', ['team' => $role->id])
            ->with('success', 'تم إنشاء الفريق بنجاح');
    }

    public function update(Request $request, Role $role)
    {
        $isSystem = in_array($role->name, self::SYSTEM_ROLES, true);
        $features = $this->currentTenantFeatures();
        $visibleNames = PermissionCatalog::permissionNamesForFeatures($features);

        $validated = $request->validate([
            'name' => [
                $isSystem ? 'nullable' : 'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_\-\s]+$/i',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'display_name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($visibleNames)],
        ]);

        $role->display_name = $validated['display_name'];
        $role->description = $validated['description'] ?? null;

        if (! $isSystem && ! empty($validated['name'])) {
            $role->name = trim($validated['name']);
        }

        $role->save();

        // مهم جداً: الأدوار مشتركة بين كل الـ tenants.
        // نحدّث فقط الصلاحيات الظاهرة لهذا الـ tenant،
        // ونُبقي صلاحيات الميزات غير الظاهرة هنا، ونُعيد اشتقاق الصلاحيات القديمة من الجديدة.
        $submitted = collect($validated['permissions'] ?? []);
        $legacyNames = PermissionCatalog::legacyPermissionNames();

        $preserved = $role->permissions
            ->pluck('name')
            ->reject(fn (string $name) => in_array($name, $visibleNames, true))
            ->reject(fn (string $name) => in_array($name, $legacyNames, true));

        $role->syncPermissions(
            collect($this->withLegacyPermissions($submitted->all()))
                ->merge($preserved)
                ->unique()
                ->values()
                ->all()
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index', ['team' => $role->id])
            ->with('success', 'تم حفظ بيانات الفريق');
    }

    public function destroy(Role $role)
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->with('error', 'لا يمكن حذف الأدوار الأساسية للنظام');
        }

        $tenantId = Auth::user()->tenant_id;
        $assignedCount = User::role($role->name)->where('tenant_id', $tenantId)->count();

        if ($assignedCount > 0) {
            return back()->with('error', 'لا يمكن حذف فريق مرتبط بمستخدمين. انقل المستخدمين أولاً');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles.index')
            ->with('success', 'تم حذف الفريق');
    }

    /**
     * @return array<string, bool>
     */
    private function currentTenantFeatures(): array
    {
        $tenant = Tenant::query()->find(Auth::user()?->tenant_id);

        return TenantFeatures::for($tenant);
    }

    /**
     * اشتقاق الصلاحيات القديمة من الجديدة للتوافق مع الكود الحالي.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function withLegacyPermissions(array $permissions): array
    {
        $submitted = collect($permissions);
        $legacyDerived = [];

        foreach (PermissionCatalog::legacyMap() as $legacy => $mapped) {
            if ($submitted->intersect($mapped)->isNotEmpty()) {
                $legacyDerived[] = $legacy;
            }
        }

        return $submitted->merge($legacyDerived)->unique()->values()->all();
    }

    /**
     * @return array<int, int>
     */
    private function tenantUserCountsByRole(?int $tenantId): array
    {
        if ($tenantId === null) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', (new User)->getMorphClass());
            })
            ->selectRaw('model_has_roles.role_id, COUNT(DISTINCT users.id) as aggregate')
            ->groupBy('model_has_roles.role_id')
            ->pluck('aggregate', 'role_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  list<string>  $visibleNames
     */
    private function transformRole(Role $role, int $usersCount, array $visibleNames): array
    {
        $allNames = $role->permissions->pluck('name')->values();
        // في الواجهة نعرض فقط الصلاحيات الظاهرة لهذا الـ tenant
        $visibleAssigned = $allNames->filter(fn (string $n) => in_array($n, $visibleNames, true))->values()->all();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'display_name' => $role->display_name ?: $this->fallbackDisplayName($role->name),
            'description' => $role->description ?? '',
            'permissions' => $visibleAssigned,
            'permissions_count' => count($visibleAssigned),
            'users_count' => $usersCount,
            'is_system' => in_array($role->name, self::SYSTEM_ROLES, true),
            'initial' => mb_substr($role->display_name ?: $this->fallbackDisplayName($role->name), 0, 1),
        ];
    }

    private function fallbackDisplayName(string $name): string
    {
        return match ($name) {
            'admin' => 'مدير',
            'cashier' => 'كاشير',
            'super admin' => 'سوبر أدمن',
            'barista' => 'باريستا',
            'hr' => 'مسؤول الموظفين',
            default => $name,
        };
    }
}
