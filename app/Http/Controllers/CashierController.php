<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Dompdf\Options;
use Mpdf\Mpdf;


use Illuminate\Http\Request;

class CashierController extends Controller
{
    public function index()
    {
        $products = Product::all();
        return inertia('Cashier', ['products' => $products]);
    }

    public function checkout(Request $request)
    {
        //comment
        $request->validate([
            'items' => 'required|array',
            'total' => 'required|numeric|min:0',
        ]);

        // إنشاء الطلب
        $order = Order::create([
            'total' => $request->total,
        ]);

        // حفظ عناصر الطلب
        foreach ($request->items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
            ]);
        }

        return response()->json([
            'message' => 'تم تسجيل الطلب بنجاح!',
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
            
}
