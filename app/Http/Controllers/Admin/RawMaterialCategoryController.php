<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RawMaterialCategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/RawMaterialCategories/Index', [
            'categories' => Category::forRawMaterials()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        Category::create([
            'name' => $request->name,
            'scope' => Category::SCOPE_RAW,
        ]);

        return redirect()->route('admin.raw-material-categories.index')->with('message', 'تمت إضافة فئة المواد الخام بنجاح');
    }

    public function update(Request $request, Category $category)
    {
        if ($category->scope !== Category::SCOPE_RAW) {
            abort(404);
        }

        $request->validate(['name' => 'required|string|max:255']);
        $category->update(['name' => $request->name]);

        return redirect()->route('admin.raw-material-categories.index')->with('message', 'تم تحديث الفئة بنجاح');
    }

    public function destroy(Category $category)
    {
        if ($category->scope !== Category::SCOPE_RAW) {
            abort(404);
        }

        $category->delete();

        return redirect()->route('admin.raw-material-categories.index')->with('message', 'تم حذف الفئة بنجاح');
    }
}
