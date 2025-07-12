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
use App\Models\CashierShift;
use Illuminate\Support\Facades\Auth;

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
        
        // تحسين الأداء: استخدام bulk operations بدلاً من عمليات فردية
        DB::transaction(function () use ($data, &$order) {
            // الحصول على الوردية النشطة للمستخدم
            $activeShift = CashierShift::getActiveShift(Auth::id());
            
            // 1. إنشاء الطلب
            $orderData = [
                'total' => $data['total_price'],
                'payment_method' => $data['payment_method'],
                'status' => 'completed',
            ];
            
            // إضافة معرف الوردية إذا كانت موجودة
            if ($activeShift) {
                $orderData['cashier_shift_id'] = $activeShift->id;
            }
            
            $order = Order::create($orderData);

            // 2. إنشاء عناصر الطلب بشكل جماعي
            $orderItems = [];
            foreach ($data['items'] as $item) {
                $orderItems[] = [
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'size' => $item['size'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            OrderItem::insert($orderItems);

            // 3. تحسين عمليات المخزون: تجميع العمليات
            $stockUpdates = [];
            $stockMovements = [];
            
            // تجميع جميع المنتجات المطلوبة مسبقاً مع تحسين الاستعلام
            $productIds = collect($data['items'])->pluck('product_id')->unique();
            $products = Product::select('id', 'type', 'stock')
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');
            
            // تجميع جميع المكونات المطلوبة مسبقاً
            $finishedProductIds = $products->where('type', 'finished')->keys();
            $ingredients = collect();
            if ($finishedProductIds->isNotEmpty()) {
                $ingredients = DB::table('ingredients')
                    ->select('finished_product_id', 'raw_material_id', 'quantity_consumed', 'size')
                    ->whereIn('finished_product_id', $finishedProductIds)
                    ->get()
                    ->groupBy('finished_product_id');
            }
            
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) continue;

                // A) إذا كان منتج نهائي، ابحث عن المكونات للمقاس المحدد
                if ($product->type === 'finished') {
                    $productIngredients = $ingredients->get($product->id, collect());
                    $ingredientsForSize = $productIngredients->where('size', $item['size']);

                    foreach ($ingredientsForSize as $ingredient) {
                        $quantityToDeduct = $item['quantity'] * $ingredient->quantity_consumed;
                        
                        // تجميع تحديثات المخزون
                        if (!isset($stockUpdates[$ingredient->raw_material_id])) {
                            $stockUpdates[$ingredient->raw_material_id] = 0;
                        }
                        $stockUpdates[$ingredient->raw_material_id] -= $quantityToDeduct;
                        
                        // تجميع حركات المخزون
                        $stockMovements[] = [
                            'product_id' => $ingredient->raw_material_id,
                            'quantity' => -$quantityToDeduct,
                            'type' => 'sale_deduction',
                            'related_order_id' => $order->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                } 
                // B) إذا كان منتج بسيط (مادة خام تباع مباشرة)
                else if ($product->type === 'raw' && $product->stock !== null) {
                    if (!isset($stockUpdates[$product->id])) {
                        $stockUpdates[$product->id] = 0;
                    }
                    $stockUpdates[$product->id] -= $item['quantity'];
                    
                    $stockMovements[] = [
                        'product_id' => $product->id,
                        'quantity' => -$item['quantity'],
                        'type' => 'sale_deduction',
                        'related_order_id' => $order->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            
            // تنفيذ تحديثات المخزون بشكل جماعي
            foreach ($stockUpdates as $productId => $change) {
                DB::table('products')->where('id', $productId)->increment('stock', $change);
            }
            
            // إدراج حركات المخزون بشكل جماعي
            if (!empty($stockMovements)) {
                StockMovement::insert($stockMovements);
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
    // تحسين الأداء: استخدام select محدد بدلاً من تحميل كل البيانات
    $order = Order::select('id', 'total', 'created_at')
        ->with(['items' => function($query) {
            $query->select('order_id', 'product_name', 'quantity', 'price', 'size');
        }])
        ->findOrFail($orderId);
    
    return view('invoice-html', compact('order'));
}


            
}
