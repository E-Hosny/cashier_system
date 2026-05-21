<?php
namespace App\Http\Controllers;

use App\Models\CustomPurchaseItem;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        $rawMaterials = Product::where('type', 'raw')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'unit',
                'purchase_unit',
                'consume_unit',
                'quantity_per_unit',
            ]);

        $customPurchaseItems = CustomPurchaseItem::orderBy('name')->get(['id', 'name', 'unit']);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'selectedDate' => $request->date,
            'from' => $request->from,
            'to' => $request->to,
            'rawMaterials' => $rawMaterials,
            'customPurchaseItems' => $customPurchaseItems,
        ]);
    }

    public function storeCustomItem(Request $request): RedirectResponse
    {
        if (BranchContext::hasBranch()) {
            return redirect()->route('dashboard')
                ->with('error', 'المشتريات متاحة من العرض المركزي فقط.');
        }

        $tenantId = auth()->user()->tenant_id;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('custom_purchase_items', 'name')->when(
                    $tenantId,
                    fn ($rule) => $rule->where('tenant_id', $tenantId)
                ),
            ],
            'unit' => 'nullable|string|max:50',
        ]);

        CustomPurchaseItem::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'unit' => $data['unit'] ?? null,
        ]);

        return redirect()->route('purchases.index')->with('success', 'تمت إضافة بند المشتريات المخصص.');
    }

    public function destroyCustomItem(CustomPurchaseItem $customPurchaseItem): RedirectResponse
    {
        if (BranchContext::hasBranch()) {
            return redirect()->route('dashboard')
                ->with('error', 'المشتريات متاحة من العرض المركزي فقط.');
        }

        $customPurchaseItem->delete();

        return redirect()->route('purchases.index')->with('success', 'تم حذف بند المشتريات المخصص.');
    }

    public function store(Request $request): RedirectResponse
    {
        if (BranchContext::hasBranch()) {
            return redirect()->route('dashboard')
                ->with('error', 'المشتريات متاحة من العرض المركزي فقط.');
        }

        $tenantId = auth()->user()->tenant_id;

        $request->validate([
            'purchase_kind' => 'required|in:raw,custom',
            'supplier_name' => 'nullable|string|max:255',
            'description' => 'required_if:purchase_kind,raw|nullable|string|max:255',
            'custom_purchase_item_id' => [
                'required_if:purchase_kind,custom',
                'nullable',
                'integer',
                Rule::exists('custom_purchase_items', 'id')->when(
                    $tenantId,
                    fn ($rule) => $rule->where('tenant_id', $tenantId)
                ),
            ],
            'quantity' => 'nullable|numeric|min:0.001',
            'total_amount' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'purchase_unit' => 'nullable|string|max:50',
        ]);

        $kind = $request->input('purchase_kind');
        $description = $request->description;
        $customItemId = null;

        if ($kind === Purchase::KIND_CUSTOM) {
            $customItem = CustomPurchaseItem::findOrFail($request->custom_purchase_item_id);
            $description = $customItem->name;
            $customItemId = $customItem->id;
        }

        $purchase = Purchase::create([
            'tenant_id' => auth()->user()->tenant_id,
            'purchase_kind' => $kind,
            'custom_purchase_item_id' => $customItemId,
            'supplier_name' => $request->supplier_name,
            'description' => $description,
            'quantity' => $request->quantity,
            'total_amount' => $request->total_amount,
            'purchase_date' => $request->purchase_date,
        ]);

        if ($kind === Purchase::KIND_RAW && $request->quantity && $description) {
            $product = Product::where('type', 'raw')
                ->where('name', $description)
                ->first();

            if ($product) {
                $quantityToAdd = self::stockAdditionFromPurchaseQuantity(
                    $product,
                    (float) $request->quantity,
                    $request->purchase_unit
                );

                $product->increment('stock', $quantityToAdd);
                StockMovement::create([
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
        Product $product,
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
