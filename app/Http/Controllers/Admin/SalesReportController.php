<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Inertia\Inertia;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        // الحصول على تاريخ اليوم أو التاريخ المحدد من المستخدم
        $date = $request->input('date', Carbon::today()->toDateString());

        // جلب المنتجات المباعة بناءً على التاريخ
        $sales = OrderItem::whereHas('order', function ($query) use ($date) {
            $query->whereDate('created_at', $date);
        })
        ->with('product')
        ->selectRaw('product_id, SUM(quantity) as total_quantity, AVG(price) as unit_price, SUM(quantity * price) as total_price')
        ->groupBy('product_id')
        ->get();

        // حساب إجمالي المبيعات في اليوم المحدد
        $totalSales = $sales->sum('total_price');

        return Inertia::render('Admin/SalesReport', [
            'sales' => $sales,
            'date' => $date,
            'totalSales' => $totalSales,
        ]);
    }
}
