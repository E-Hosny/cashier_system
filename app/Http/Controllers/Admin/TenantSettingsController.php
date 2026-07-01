<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TenantSettingsController extends Controller
{
    public function index(): Response
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $tenant = $this->currentTenant();

        return Inertia::render('Admin/TenantSettings/Index', [
            'tenantName' => $tenant->name,
            'logoUrl' => $tenant->logo_url,
        ]);
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:2048'],
        ]);

        $tenant = $this->currentTenant();

        if ($tenant->logo_path && Storage::disk('public')->exists($tenant->logo_path)) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $path = $request->file('logo')->store('tenant-logos/'.$tenant->id, 'public');
        $tenant->update(['logo_path' => $path]);

        return redirect()->route('admin.tenant-settings.index')
            ->with('success', 'تم رفع الشعار بنجاح.');
    }

    public function destroyLogo(): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $tenant = $this->currentTenant();

        if ($tenant->logo_path && Storage::disk('public')->exists($tenant->logo_path)) {
            Storage::disk('public')->delete($tenant->logo_path);
        }

        $tenant->update(['logo_path' => null]);

        return redirect()->route('admin.tenant-settings.index')
            ->with('success', 'تم حذف الشعار.');
    }

    protected function currentTenant(): Tenant
    {
        $tenantId = Auth::user()->tenant_id;
        abort_if(! $tenantId, 403, 'لا يوجد حساب تجاري مرتبط بمستخدمك.');

        return Tenant::findOrFail($tenantId);
    }
}
