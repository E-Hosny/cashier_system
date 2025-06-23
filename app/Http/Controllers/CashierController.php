<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\StockMovement;

use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class CashierController extends Controller
{
    //comment
  public function index()
{
    $products = Product::with('category')
        ->where('type', 'finished')
        ->latest()->get()->append('available_sizes');
    // Ensure size_variants is always an array
    $products->transform(function ($product) {
        if (is_null($product->size_variants)) {
            $product->size_variants = [];
        }
        return $product;
    });
    $categories = Category::all();
    return Inertia::render('Cashier', [
        'products' => $products,
        'categories' => $categories,
    ]);
}


    public function store(Request $request)
    {
        $data = $request->validate([
            'total_price' => 'required|numeric',
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.product_name' => 'required|string',
            'items.*.size' => 'nullable|string',
        ]);

        $order = null;
        DB::transaction(function () use ($data, &$order) {
            // 1. Create the order
            $order = Order::create([
                'total' => $data['total_price'],
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
            ]);

            // 2. Create order items
            $order->items()->createMany($data['items']);

            // 3. Eager load relationships for stock deduction
            $order->load('items.product.ingredients');

            // 4. Deduct stock for each item sold
            foreach ($order->items as $item) {
                $product = $item->product;

                // A) If it's a finished product, find ingredients for the specific size sold
                if ($product->type === 'finished') {
                    // Find ingredients that match the product ID and the specific size sold
                    $ingredientsForSize = DB::table('ingredients')
                        ->where('finished_product_id', $product->id)
                        ->where('size', $item->size) // <--- Key change: filter by size
                        ->get();

                    if ($ingredientsForSize->isNotEmpty()) {
                        foreach ($ingredientsForSize as $ingredient) {
                            $quantityToDeduct = $item->quantity * $ingredient->quantity_consumed;
                            
                            // Decrement stock of the raw material
                            DB::table('products')->where('id', $ingredient->raw_material_id)->decrement('stock', $quantityToDeduct);

                            // Record the stock movement
                            StockMovement::create([
                                'product_id' => $ingredient->raw_material_id,
                                'quantity' => -$quantityToDeduct,
                                'type' => 'sale_deduction',
                                'related_order_id' => $order->id,
                            ]);
                        }
                    }
                } 
                // B) If it's a simple product (raw material sold directly)
                else if ($product->type === 'raw' && $product->stock !== null) {
                     // Decrement stock of the product itself
                     $product->decrement('stock', $item->quantity);

                     // Record the stock movement
                     StockMovement::create([
                         'product_id' => $product->id,
                         'quantity' => -$item->quantity,
                         'type' => 'sale_deduction',
                         'related_order_id' => $order->id,
                     ]);
                }
            }
        });

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح!',
            'order_id' => $order->id,
        ]);
    }

    public function invoice($orderId)
    {
        $order = Order::with('items.product')->findOrFail($orderId);
    
        $mpdf = new Mpdf([
            'format' => [80, 297],
            'default_font' => 'Arial',
            'mode' => 'utf-8',
        ]);
    
        // $html = view('invoice', compact('order'))->render();
        $html = view('Invoice', compact('order'))->render();

        $mpdf->WriteHTML($html);
        
        return $mpdf->Output("invoice_{$order->id}.pdf", 'I');
    }

public function invoiceHtml($orderId)
{
    $order = Order::with('items.product')->findOrFail($orderId);
    return view('invoice-html', compact('order'));
}


            
}
