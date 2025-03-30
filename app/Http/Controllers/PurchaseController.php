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
        $date = $request->query('date', Carbon::today()->toDateString());

        $purchases = Purchase::whereDate('purchase_date', $date)
            ->orderBy('purchase_date', 'desc')
            ->get();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'selectedDate' => $date
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_name' => 'nullable|string|max:255',
            'product_name' => 'required|string|max:255',
            'quantity' => 'nullable|integer|min:1',
            'total_amount' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
        ]);

        Purchase::create([
            'tenant_id' => auth()->user()->tenant_id,
            'supplier_name' => $request->supplier_name,
            'product_name' => $request->product_name,
            'quantity' => $request->quantity,
            'total_amount' => $request->total_amount,
            'purchase_date' => $request->purchase_date,
        ]);

        return redirect()->route('purchases.index')->with('success', 'تم إضافة المشتريات بنجاح.');
    }
}

