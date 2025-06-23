<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RawMaterialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $rawMaterials = Product::where('type', 'raw')->latest()->get();
        return Inertia::render('Admin/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/RawMaterials/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $data['type'] = 'raw';
        Product::create($data);

        return redirect()->route('admin.raw-materials.index')->with('success', 'تمت إضافة المادة الخام بنجاح.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $raw_material)
    {
        return Inertia::render('Admin/RawMaterials/Edit', [
            'rawMaterial' => $raw_material,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $raw_material)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
        ]);

        $raw_material->update($data);

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم تحديث المادة الخام بنجاح.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $raw_material)
    {
        // Add check if material is used in any finished product before deleting
        $raw_material->delete();
        return redirect()->route('admin.raw-materials.index')->with('success', 'تم حذف المادة الخام بنجاح.');
    }
}
