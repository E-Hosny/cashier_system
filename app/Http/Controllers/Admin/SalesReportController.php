<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use Carbon\Carbon;
use Inertia\Inertia;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        // الحصول على فترة التواريخ من المستخدم أو تعيين اليوم الحالي كافتراضي
        $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
        $dateTo = $request->input('date_to', null);
        $categoryId = $request->input('category_id', null);
        $productId = $request->input('product_id', null);

        // بناء استعلام المبيعات
        $salesQuery = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
            if ($dateTo) {
                // فترة من - إلى
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->startOfDay(),
                    Carbon::parse($dateTo)->endOfDay()
                ]);
            } else {
                // يوم واحد فقط
                $query->whereDate('created_at', $dateFrom);
            }
        })
        ->with(['product.category']);

        // تصفية حسب الفئة إذا تم تحديدها
        if ($categoryId) {
            $salesQuery->whereHas('product', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });
        }

        // تصفية حسب المنتج إذا تم تحديده
        if ($productId) {
            $salesQuery->where('product_id', $productId);
        }

        $sales = $salesQuery
            ->selectRaw('product_id, size, SUM(quantity) as total_quantity, AVG(price) as unit_price, SUM(quantity * price) as total_price')
            ->groupBy('product_id', 'size')
            ->get();

        // حساب إجمالي المشتريات والمصروفات
        if ($dateTo) {
            $totalPurchases = \App\Models\Purchase::whereBetween('purchase_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString()
            ])->sum('total_amount');

            $totalExpenses = \App\Models\Expense::whereBetween('expense_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString()
            ])->sum('amount');
        } else {
            $totalPurchases = \App\Models\Purchase::whereDate('purchase_date', $dateFrom)->sum('total_amount');
            $totalExpenses = \App\Models\Expense::whereDate('expense_date', $dateFrom)->sum('amount');
        }

        $totalSales = $sales->sum('total_price');

        // جلب قوائم الفئات والمنتجات للتصفية
        $categories = Category::orderBy('name')->get();
        $products = Product::where('type', 'finished')
            ->when($categoryId, function ($query) use ($categoryId) {
                return $query->where('category_id', $categoryId);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return Inertia::render('Admin/SalesReport', [
            'sales' => $sales,
            'date' => $dateFrom,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_id' => $categoryId,
            'product_id' => $productId,
            'totalSales' => $totalSales,
            'totalPurchases' => $totalPurchases,
            'totalExpenses' => $totalExpenses,
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
