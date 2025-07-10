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
        // الحصول على فترة التواريخ من المستخدم أو تعيين اليوم الحالي كافتراضي
        $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
        $dateTo = $request->input('date_to', null);

        if ($dateTo) {
            // فترة من - إلى
            $sales = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ]);
            })
            ->with('product')
            ->selectRaw('product_id, size, SUM(quantity) as total_quantity, AVG(price) as unit_price, SUM(quantity * price) as total_price')
            ->groupBy('product_id', 'size')
            ->get();

            // حساب إجمالي المبيعات من جدول الطلبات (بعد الخصم)
            $totalSales = Order::whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ])->sum('total');

            $totalPurchases = \App\Models\Purchase::whereBetween('purchase_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString()
            ])->sum('total_amount');

            $totalExpenses = \App\Models\Expense::whereBetween('expense_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString()
            ])->sum('amount');
        } else {
            // يوم واحد فقط
            $sales = OrderItem::whereHas('order', function ($query) use ($dateFrom) {
                $query->whereDate('created_at', $dateFrom);
            })
            ->with('product')
            ->selectRaw('product_id, size, SUM(quantity) as total_quantity, AVG(price) as unit_price, SUM(quantity * price) as total_price')
            ->groupBy('product_id', 'size')
            ->get();

            // حساب إجمالي المبيعات من جدول الطلبات (بعد الخصم)
            $totalSales = Order::whereDate('created_at', $dateFrom)->sum('total');

            $totalPurchases = \App\Models\Purchase::whereDate('purchase_date', $dateFrom)->sum('total_amount');
            $totalExpenses = \App\Models\Expense::whereDate('expense_date', $dateFrom)->sum('amount');
        }

        return Inertia::render('Admin/SalesReport', [
            'sales' => $sales,
            'date' => $dateFrom,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'totalSales' => $totalSales,
            'totalPurchases' => $totalPurchases,
            'totalExpenses' => $totalExpenses,
        ]);
    }
}
