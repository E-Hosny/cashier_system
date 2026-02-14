<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DisplayScreenConfig;
use App\Models\DisplayScreenSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DisplayScreenController extends Controller
{
    public function index()
    {
        $slides = DisplayScreenSlide::orderBy('sort_order')->get()->map(function ($slide) {
            return [
                'id' => $slide->id,
                'path' => $slide->path,
                'url' => asset('storage/' . $slide->path),
                'sort_order' => $slide->sort_order,
            ];
        });
        $config = DisplayScreenConfig::first();
        if (!$config) {
            $config = DisplayScreenConfig::create(['interval_seconds' => 3]);
        }

        return Inertia::render('Admin/DisplayScreen/Index', [
            'slides' => $slides,
            'interval_seconds' => (int) $config->interval_seconds,
        ]);
    }

    public function storeSlide(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        $path = $request->file('image')->store('display-screen', 'public');
        $maxOrder = DisplayScreenSlide::max('sort_order') ?? 0;
        DisplayScreenSlide::create([
            'path' => $path,
            'sort_order' => $maxOrder + 1,
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

    public function destroySlide(DisplayScreenSlide $slide)
    {
        if ($slide->path && Storage::disk('public')->exists($slide->path)) {
            Storage::disk('public')->delete($slide->path);
        }
        $slide->delete();
        return redirect()->route('admin.display-screen.index')->with('success', 'تم حذف الصورة.');
    }

    public function updateConfig(Request $request)
    {
        $request->validate([
            'interval_seconds' => 'required|integer|min:1|max:60',
        ]);

        $config = DisplayScreenConfig::first();
        if (!$config) {
            $config = DisplayScreenConfig::create(['interval_seconds' => 3]);
        }
        $config->update(['interval_seconds' => $request->interval_seconds]);

        return redirect()->route('admin.display-screen.index')->with('success', 'تم حفظ المدة.');
    }

    /**
     * Public full-screen display (no auth).
     */
    public function show()
    {
        $slides = DisplayScreenSlide::orderBy('sort_order')->get();
        $config = DisplayScreenConfig::first();
        if (!$config) {
            $config = DisplayScreenConfig::create(['interval_seconds' => 3]);
        }
        $intervalSeconds = (int) $config->interval_seconds;
        $imageUrls = $slides->map(fn ($slide) => asset('storage/' . $slide->path))->values()->all();

        return view('display-screen.show', [
            'imageUrls' => $imageUrls,
            'intervalSeconds' => $intervalSeconds,
        ]);
    }
}
