<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
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
            'price_per_piece' => 'required|numeric|min:0',
            'consume_unit' => 'required|string|in:مللي,جرام,قطعة,كوب',
            'quantity_per_unit' => 'required|numeric|min:0.001',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
            'unit_consume_price' => 'required|numeric|min:0',
        ]);

        $data['type'] = 'raw';
        $data['purchase_unit'] = $data['unit'];
        $data['purchase_quantity'] = 1;
        $data['purchase_price'] = $data['price_per_piece'];
        unset($data['price_per_piece']);
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
            'price_per_piece' => 'required|numeric|min:0',
            'consume_unit' => 'required|string|in:مللي,جرام,قطعة,كوب',
            'quantity_per_unit' => 'required|numeric|min:0.001',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
            'unit_consume_price' => 'required|numeric|min:0',
        ]);

        $data['purchase_unit'] = $data['unit'];
        $data['purchase_quantity'] = 1;
        $data['purchase_price'] = $data['price_per_piece'];
        unset($data['price_per_piece']);
        $raw_material->update($data);

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم تحديث المادة الخام بنجاح.');
    }

    /**
     * Show form for adding extra quantity to a raw material.
     */
    public function addQuantityForm(Product $raw_material)
    {
        return Inertia::render('Admin/RawMaterials/AddQuantity', [
            'rawMaterial' => $raw_material,
        ]);
    }

    /**
     * Store added quantity for a raw material (in pieces).
     */
    public function addQuantity(Request $request, Product $raw_material)
    {
        $data = $request->validate([
            'quantity_units' => 'required|numeric|min:0.001',
            'note' => 'nullable|string|max:255',
        ]);

        $unitsToAdd = (float) $data['quantity_units'];
        $perUnit = $raw_material->quantity_per_unit ?: 1;
        $quantityToAdd = $unitsToAdd * $perUnit;

        $raw_material->increment('stock', $quantityToAdd);

        StockMovement::create([
            'product_id' => $raw_material->id,
            'quantity' => $quantityToAdd,
            'type' => 'manual_addition',
            'related_order_id' => null,
            'related_purchase_id' => null,
        ]);

        return redirect()->route('admin.raw-materials.index')->with('success', 'تمت إضافة الكمية للمخزون بنجاح.');
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
