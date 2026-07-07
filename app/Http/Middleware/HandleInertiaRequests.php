<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $branchContext = [
            'activeBranchId' => BranchContext::id(),
            'activeBranchName' => null,
            'isSuperAdminHub' => false,
            'branches' => [],
            'printerSettings' => [
                'mode' => 'single',
                'method' => 'browser',
                'customer_printer' => null,
                'staff_printer' => null,
            ],
        ];

        $tenantBranding = [
            'name' => null,
            'logoUrl' => null,
        ];

        if ($user && $user->tenant_id) {
            $bid = BranchContext::id();
            if ($bid) {
                $branch = Branch::find($bid);
                $branchContext['activeBranchName'] = $branch?->name;
                $branchContext['printerSettings'] = $branch?->normalizedPrinterSettings() ?? [
                    'mode' => 'single',
                    'method' => 'browser',
                    'customer_printer' => null,
                    'staff_printer' => null,
                ];
            }

            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                $tenantBranding = [
                    'name' => $tenant->name,
                    'logoUrl' => $tenant->logo_url,
                ];
            }

            if ($user->hasRole('super admin')) {
                $branchContext['isSuperAdminHub'] = ! session('active_branch_id');
                $branchContext['branches'] = Branch::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->values()
                    ->all();
            }
        }

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ] : null,
            ],
            'branchContext' => $branchContext,
            'tenantBranding' => $tenantBranding,
        ]);
    }
}
