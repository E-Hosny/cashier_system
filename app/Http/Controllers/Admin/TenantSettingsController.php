<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Support\QzTrustInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
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
            'qzKeysConfigured' => QzTrustInstaller::keysExist(),
            'branches' => Branch::query()
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'printer_settings' => $branch->normalizedPrinterSettings(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function updateBranchPrinters(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);
        abort_if((int) $branch->tenant_id !== (int) Auth::user()->tenant_id, 403);

        $data = $request->validate([
            'mode' => 'required|in:single,dual',
            'method' => 'required|in:qz,browser',
            'customer_printer' => 'nullable|string|max:255',
            'staff_printer' => 'nullable|string|max:255',
        ]);

        if ($data['method'] === 'qz' && empty($data['customer_printer'])) {
            return back()->withErrors([
                'customer_printer' => 'حدد اسم طابعة الزبون عند استخدام QZ Tray.',
            ]);
        }

        if ($data['mode'] === 'dual' && $data['method'] === 'qz' && empty($data['staff_printer'])) {
            return back()->withErrors([
                'staff_printer' => 'حدد اسم طابعة العامل عند تفعيل طابعتين.',
            ]);
        }

        $branch->update([
            'printer_settings' => [
                'mode' => $data['mode'],
                'method' => $data['method'],
                'customer_printer' => $data['customer_printer'] ?: null,
                'staff_printer' => $data['staff_printer'] ?: null,
            ],
        ]);

        return redirect()->route('admin.tenant-settings.index')
            ->with('success', "تم حفظ إعدادات الطباعة لفرع «{$branch->name}».");
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

    public function uploadQzKeys(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);

        $request->validate([
            'certificate' => ['required', 'file', 'max:64'],
            'private_key' => ['required', 'file', 'max:64'],
        ]);

        $certContents = file_get_contents($request->file('certificate')->getRealPath());
        $keyContents = file_get_contents($request->file('private_key')->getRealPath());

        if (! str_contains($certContents, 'BEGIN CERTIFICATE')) {
            return back()->withErrors(['certificate' => 'ملف الشهادة غير صالح.']);
        }

        if (! str_contains($keyContents, 'BEGIN') || ! str_contains($keyContents, 'PRIVATE KEY')) {
            return back()->withErrors(['private_key' => 'ملف المفتاح الخاص غير صالح.']);
        }

        $privateKey = openssl_pkey_get_private($keyContents);

        if ($privateKey === false) {
            return back()->withErrors(['private_key' => 'تعذر قراءة المفتاح الخاص.']);
        }

        $dir = dirname(config('qz.certificate_path'));
        File::ensureDirectoryExists($dir);

        File::put(config('qz.certificate_path'), $certContents);
        File::put(config('qz.private_key_path'), $keyContents);

        return redirect()->route('admin.tenant-settings.index')
            ->with('success', 'تم رفع مفاتيح QZ. ثبّت الثقة على جهاز الكاشير (تحميل حزمة التثبيت أو Site Manager).');
    }

    protected function currentTenant(): Tenant
    {
        $tenantId = Auth::user()->tenant_id;
        abort_if(! $tenantId, 403, 'لا يوجد حساب تجاري مرتبط بمستخدمك.');

        return Tenant::findOrFail($tenantId);
    }
}
