<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RawMaterialPendingLabel;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RawMaterialController extends Controller
{
    private function userHasAnyRole(array $roles): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function requireAnyRole(array $roles): void
    {
        if (! $this->userHasAnyRole($roles)) {
            abort(403, 'غير مصرح لك بالوصول لهذه الصفحة');
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->requireAnyRole(['admin', 'super admin', 'cashier']);

        $rawMaterials = Product::where('type', 'raw')->latest()->get();
        $pendingSums = RawMaterialPendingLabel::query()
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->selectRaw('product_id, SUM(piece_count) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');
        $rawMaterials = $rawMaterials->map(function (Product $m) use ($pendingSums) {
            $m->pending_pieces = (float) ($pendingSums[$m->id] ?? 0);

            return $m;
        });

        return Inertia::render('Admin/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->requireAnyRole(['super admin']);

        return Inertia::render('Admin/RawMaterials/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->requireAnyRole(['super admin']);

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
        $product = Product::create($data);
        if (empty($product->barcode)) {
            $product->forceFill(['barcode' => 'RM-'.strtoupper(Str::ulid())])->save();
        }

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
        $this->requireAnyRole(['admin', 'super admin']);

        return Inertia::render('Admin/RawMaterials/Edit', [
            'rawMaterial' => $raw_material,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

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
        if ($raw_material->type === 'raw' && empty($raw_material->barcode)) {
            $raw_material->forceFill(['barcode' => 'RM-'.strtoupper(Str::ulid())])->save();
        }

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم تحديث المادة الخام بنجاح.');
    }

    /**
     * Create a pending label batch (does not change stock until received).
     */
    public function storeLabel(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if ($raw_material->type !== 'raw') {
            abort(404);
        }

        $data = $request->validate([
            'piece_count' => 'required|numeric|min:0.001',
        ]);

        $pieces = (float) $data['piece_count'];
        $perUnit = (float) ($raw_material->quantity_per_unit ?: 1);
        $consumeAmount = $pieces * $perUnit;

        $label = RawMaterialPendingLabel::create([
            'product_id' => $raw_material->id,
            'label_code' => strtoupper(Str::ulid()),
            'piece_count' => $pieces,
            'consume_amount' => $consumeAmount,
            'status' => RawMaterialPendingLabel::STATUS_PENDING,
        ]);

        // When called from the list modal (AJAX), return JSON and do not redirect.
        if ($request->isXmlHttpRequest()) {
            $label->loadMissing('product');

            return response()->json([
                'label_code' => $label->label_code,
                'piece_count' => (float) $label->piece_count,
                'consume_amount' => (float) $label->consume_amount,
                'status' => $label->status,
                'product_name' => $label->product?->name,
                'unit' => $label->product?->unit,
                'consume_unit' => $label->product?->consume_unit,
            ]);
        }

        return redirect()->route('admin.raw-materials.labels.print', $label);
    }

    /**
     * Printable label page (barcode encodes label_code).
     */
    public function printLabel(RawMaterialPendingLabel $label)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        $label->loadMissing('product');

        return Inertia::render('Admin/RawMaterials/PrintLabel', [
            'label' => [
                'id' => $label->id,
                'label_code' => $label->label_code,
                'piece_count' => (float) $label->piece_count,
                'consume_amount' => (float) $label->consume_amount,
                'status' => $label->status,
            ],
            'productName' => $label->product?->name ?? '',
            'unit' => $label->product?->unit ?? '',
            'consumeUnit' => $label->product?->consume_unit ?? '',
        ]);
    }

    public function receiveByBarcodeForm()
    {
        $this->requireAnyRole(['cashier', 'admin', 'super admin']);

        return Inertia::render('Admin/RawMaterials/ReceiveByBarcode');
    }

    /**
     * Receive stock by scanning / entering label_code.
     */
    public function receiveByBarcode(Request $request)
    {
        $this->requireAnyRole(['cashier', 'admin', 'super admin']);

        $data = $request->validate([
            'label_code' => 'required|string|max:64',
        ]);

        $code = strtoupper(trim($data['label_code']));

        $label = RawMaterialPendingLabel::query()
            ->where('label_code', $code)
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->first();

        if (! $label) {
            return back()->withErrors(['label_code' => 'الكود غير صالح أو تم استلامه مسبقاً.'])->withInput();
        }

        $product = $label->product;
        if (! $product || $product->type !== 'raw') {
            return back()->withErrors(['label_code' => 'المادة المرتبطة بهذا الكود غير صالحة.'])->withInput();
        }

        $amount = (float) $label->consume_amount;

        DB::transaction(function () use ($product, $label, $amount) {
            $product->increment('stock', $amount);

            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => $amount,
                'type' => 'barcode_receipt',
                'related_order_id' => null,
                'related_purchase_id' => null,
            ]);

            $label->update([
                'status' => RawMaterialPendingLabel::STATUS_RECEIVED,
                'received_at' => now(),
            ]);
        });

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم استلام الكمية وإضافتها إلى المخزون بنجاح.');
    }

    /**
     * Show form for adding extra quantity to a raw material.
     */
    public function addQuantityForm(Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        return Inertia::render('Admin/RawMaterials/AddQuantity', [
            'rawMaterial' => $raw_material,
        ]);
    }

    /**
     * Store added quantity for a raw material (in pieces).
     */
    public function addQuantity(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

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
        $this->requireAnyRole(['super admin']);

        // Add check if material is used in any finished product before deleting
        $raw_material->delete();

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم حذف المادة الخام بنجاح.');
    }
}
