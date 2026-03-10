<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisplayScreenSlide;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DisplayScreenController extends Controller
{
    public function index(Request $request)
    {
        $slides = DisplayScreenSlide::orderBy('sort_order')->get()->map(function ($slide) {
            return [
                'id' => $slide->id,
                'path' => $slide->path,
                'url' => asset('storage/' . $slide->path),
                'sort_order' => $slide->sort_order,
                'duration_seconds' => (int) ($slide->duration_seconds ?? 3),
            ];
        });

        $displayPublicUrl = '';
        $user = $request->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                // استخدام slug إن وُجد، وإلا معرّف الـ tenant ليكون الرابط دائماً خاصاً بالفرع
                $tenantParam = $tenant->slug ?: (string) $tenant->id;
                $displayPublicUrl = url()->route('display.screen', ['tenant' => $tenantParam]);
            }
        }
        if ($displayPublicUrl === '') {
            $displayPublicUrl = url()->route('display.screen', []);
        }

        return Inertia::render('Admin/DisplayScreen/Index', [
            'slides' => $slides,
            'displayPublicUrl' => $displayPublicUrl,
        ]);
    }

    public function storeSlide(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'duration_seconds' => 'required|integer|min:1|max:60',
        ]);

        $path = $request->file('image')->store('display-screen', 'public');
        $maxOrder = DisplayScreenSlide::max('sort_order') ?? 0;
        DisplayScreenSlide::create([
            'path' => $path,
            'sort_order' => $maxOrder + 1,
            'duration_seconds' => (int) $request->duration_seconds,
        ]);

        return redirect()->route('admin.display-screen.index')->with('success', 'تم رفع الصورة بنجاح.');
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:display_screen_slides,id',
            'order.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->order as $item) {
            DisplayScreenSlide::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return redirect()->route('admin.display-screen.index')->with('success', 'تم تحديث الترتيب.');
    }

    public function updateSlide(Request $request, DisplayScreenSlide $slide)
    {
        $request->validate([
            'duration_seconds' => 'required|integer|min:1|max:60',
        ]);
        $slide->update(['duration_seconds' => (int) $request->duration_seconds]);
        return redirect()->route('admin.display-screen.index')->with('success', 'تم تحديث المدة.');
    }

    public function destroySlide(DisplayScreenSlide $slide)
    {
        if ($slide->path && Storage::disk('public')->exists($slide->path)) {
            Storage::disk('public')->delete($slide->path);
        }
        $slide->delete();
        return redirect()->route('admin.display-screen.index')->with('success', 'تم حذف الصورة.');
    }

    /**
     * Public full-screen display (no auth). المحتوى حسب الـ tenant في الرابط: /display/{slug أو id}
     */
    public function show(?string $tenant = null)
    {
        $tenantModel = null;
        if ($tenant !== null && $tenant !== '') {
            $tenantModel = is_numeric($tenant)
                ? Tenant::find($tenant)
                : Tenant::where('slug', $tenant)->first();
        }
        if ($tenantModel === null && $tenant !== null && $tenant !== '') {
            return response()->view('display-screen.show', ['slides' => [], 'error' => 'tenant_not_found'], 404);
        }
        if ($tenantModel === null) {
            $tenantModel = Tenant::orderBy('id')->first();
        }

        $tenantId = $tenantModel?->id;
        $slides = [];
        if ($tenantId !== null) {
            $slides = DisplayScreenSlide::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($slide) {
                    return [
                        'url' => asset('storage/' . $slide->path),
                        'duration_seconds' => (int) ($slide->duration_seconds ?? 3),
                    ];
                })
                ->values()
                ->all();
        }

        return view('display-screen.show', [
            'slides' => $slides,
            'tenant' => $tenantModel,
        ]);
    }
}
