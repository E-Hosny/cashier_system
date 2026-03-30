<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Categories/Index', [
            'categories' => Category::forProducts()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'show_on_barista' => 'nullable|boolean',
        ]);

        $showOnBarista = $request->input('show_on_barista', true);
        Category::create([
            'name' => $request->name,
            'scope' => Category::SCOPE_PRODUCT,
            'show_on_barista' => $showOnBarista,
        ]);
        return redirect()->route('admin.categories.index')->with('message', 'تمت إضافة الفئة بنجاح');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'show_on_barista' => 'nullable|boolean',
        ]);

        $showOnBarista = $request->input('show_on_barista', true);
        $category = Category::forProducts()->findOrFail($id);
        $category->update([
            'name' => $request->name,
            'show_on_barista' => $showOnBarista,
        ]);
        return redirect()->route('admin.categories.index')->with('message', 'تم تحديث الفئة بنجاح');
    }

    public function destroy($id)
    {
        $category = Category::forProducts()->findOrFail($id);
        $category->delete();
        return redirect()->route('admin.categories.index')->with('message', 'تم حذف الفئة بنجاح');
    }
}
