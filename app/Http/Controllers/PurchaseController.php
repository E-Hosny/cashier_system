<?php
namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Purchase::orderBy('purchase_date', 'desc');

        // فلترة حسب يوم محدد
        if ($request->filled('date')) {
            $query->whereDate('purchase_date', $request->date);
        }
        // فلترة حسب فترة زمنية
        elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('purchase_date', [$request->from, $request->to]);
        }
        // فلترة من تاريخ فقط
        elseif ($request->filled('from')) {
            $query->where('purchase_date', '>=', $request->from);
        }
        // فلترة إلى تاريخ فقط
        elseif ($request->filled('to')) {
            $query->where('purchase_date', '<=', $request->to);
        }

        $purchases = $query->get();

        // جلب المواد الخام فقط
        $rawMaterials = \App\Models\Product::where('type', 'raw')->get(['id', 'name', 'unit']);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'selectedDate' => $request->date,
            'from' => $request->from,
            'to' => $request->to,
            'rawMaterials' => $rawMaterials,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'description' => 'required|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
        ]);

        $purchase = Purchase::create([
            'tenant_id' => auth()->user()->tenant_id ?? auth()->id(),
            'supplier_name' => $request->supplier_name,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'total_amount' => $request->total_amount,
            'purchase_date' => $request->purchase_date,
        ]);

        // تحديث المخزون وتسجيل حركة المخزون
        if ($request->quantity && $request->description) {
            $product = \App\Models\Product::where('name', $request->description)->first();
            if ($product) {
                $product->increment('stock', $request->quantity);
                \App\Models\StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'type' => 'purchase_addition',
                    'related_purchase_id' => $purchase->id,
                ]);
            }
        }

        return redirect()->route('purchases.index')->with('success', 'تم إضافة المشتريات بنجاح.');
    }
}

