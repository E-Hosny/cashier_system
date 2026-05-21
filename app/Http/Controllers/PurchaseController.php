<?php
namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Purchase;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PurchaseController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        if (BranchContext::hasBranch()) {
            return redirect()->route('dashboard')
                ->with('error', 'المشتريات متاحة من العرض المركزي فقط. ارجع إلى لوحة التحكم الرئيسية دون اختيار فرع.');
        }

        $query = Purchase::orderBy('created_at', 'desc');

        if ($request->filled('date')) {
            $query->whereDate('purchase_date', $request->date);
        } elseif ($request->filled('from') && $request->filled('to')) {
            $startDate = Carbon::parse($request->from)->setTime(7, 0, 0);
            $endDate = Carbon::parse($request->to)->setTime(7, 0, 0);
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($request->filled('from')) {
            $startDate = Carbon::parse($request->from)->setTime(7, 0, 0);
            $query->where('created_at', '>=', $startDate);
        } elseif ($request->filled('to')) {
            $endDate = Carbon::parse($request->to)->setTime(7, 0, 0);
            $query->where('created_at', '<=', $endDate);
        } else {
            [$startDate, $endDate] = Employee::businessDayBoundsForAnchor(Employee::businessDayAnchorFromNow());
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $purchases = $query->get();

        $rawMaterials = \App\Models\Product::where('type', 'raw')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'unit',
                'purchase_unit',
                'consume_unit',
                'quantity_per_unit',
            ]);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'selectedDate' => $request->date,
            'from' => $request->from,
            'to' => $request->to,
            'rawMaterials' => $rawMaterials,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (BranchContext::hasBranch()) {
            return redirect()->route('dashboard')
                ->with('error', 'المشتريات متاحة من العرض المركزي فقط.');
        }

        $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'quantity' => 'nullable|numeric|min:0.001',
            'total_amount' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
        ]);

        $purchase = Purchase::create([
            'tenant_id' => auth()->user()->tenant_id,
            'supplier_name' => $request->supplier_name,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'total_amount' => $request->total_amount,
            'purchase_date' => $request->purchase_date,
        ]);

        if ($request->quantity && $request->description) {
            $product = \App\Models\Product::where('type', 'raw')
                ->where('name', $request->description)
                ->first();

            if ($product) {
                $quantityToAdd = self::stockAdditionFromPurchaseQuantity(
                    $product,
                    (float) $request->quantity,
                    $request->purchase_unit
                );

                $product->increment('stock', $quantityToAdd);
                \App\Models\StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => $quantityToAdd,
                    'type' => 'purchase_addition',
                    'related_purchase_id' => $purchase->id,
                ]);
            }
        }

        return redirect()->route('purchases.index')->with('success', 'تم إضافة المشتريات بنجاح.');
    }

    /**
     * تحويل كمية المشتريات إلى مخزون (بوحدة الاستهلاك) كما في إضافة الكمية للمواد الخام.
     */
    private static function stockAdditionFromPurchaseQuantity(
        \App\Models\Product $product,
        float $quantity,
        ?string $purchaseUnit
    ): float {
        if ($product->quantity_per_unit) {
            return $quantity * (float) $product->quantity_per_unit;
        }

        $purchaseUnit = $purchaseUnit ?? $product->purchase_unit;
        $consumeUnit = $product->consume_unit;
        $conversionFactor = 1;

        if ($purchaseUnit && $consumeUnit) {
            $factors = [
                'لتر' => ['مللي' => 1000, 'لتر' => 1],
                'كجم' => ['جرام' => 1000, 'كجم' => 1],
                'قطعة' => ['قطعة' => 1],
            ];
            $conversionFactor = $factors[$purchaseUnit][$consumeUnit] ?? 1;
        }

        return $quantity * $conversionFactor;
    }
}

