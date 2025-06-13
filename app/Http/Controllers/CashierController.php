<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class CashierController extends Controller
{
    //comment
  public function index()
{
    $products = Product::with('category')->get(); // جلب المنتجات مع الفئة
    $categories =Category::latest()->get(); // جلب الفئات

    return inertia('Cashier', [
        'products' => $products,
        'categories' => $categories,
    ]);
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

public function invoiceHtml($orderId)
{
    $order = Order::with('items.product')->findOrFail($orderId);
    return view('invoice-html', compact('order'));
}


            
}
