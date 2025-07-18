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
        // الحصول على فترة التواريخ من المستخدم أو تعيين التاريخ الصحيح بناءً على الوقت الحالي
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الافتراضي بناءً على الوقت الحالي
        if ($currentHour < 7) {
            // قبل الساعة 7 صباحاً - نعرض مبيعات اليوم السابق
            $defaultDate = $now->subDay()->toDateString();
            \Log::info("قبل الساعة 7 - التاريخ الافتراضي: {$defaultDate}, الوقت الحالي: {$now->toDateTimeString()}");
        } else {
            // بعد الساعة 7 صباحاً - نعرض مبيعات اليوم الحالي
            $defaultDate = $now->toDateString();
            \Log::info("بعد الساعة 7 - التاريخ الافتراضي: {$defaultDate}, الوقت الحالي: {$now->toDateTimeString()}");
        }
        
        $dateFrom = $request->input('date_from', $defaultDate);
        $dateTo = $request->input('date_to', null);
        $categoryId = $request->input('category_id', null);
        $productId = $request->input('product_id', null);
        
        \Log::info("التاريخ النهائي المستخدم: {$dateFrom}, date_from من الطلب: " . $request->input('date_from', 'غير محدد'));

        // بناء استعلام المبيعات
        $salesQuery = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo) {
            if ($dateTo) {
                // فترة من - إلى
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                    Carbon::parse($dateTo)->setTime(7, 0, 0)    // إلى الساعة 7 صباحاً من اليوم التالي
                ]);
            } else {
                // يوم واحد فقط - من الساعة 7 صباحاً إلى الساعة 7 صباحاً من اليوم التالي
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                    Carbon::parse($dateFrom)->addDay()->setTime(7, 0, 0) // إلى الساعة 7 صباحاً من اليوم التالي
                ]);
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

        // حساب إجمالي المشتريات والمصروفات والرواتب
        if ($dateTo) {
            $totalPurchases = \App\Models\Purchase::whereBetween('purchase_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString()
            ])->sum('total_amount');

            $totalExpenses = \App\Models\Expense::whereBetween('created_at', [
                Carbon::parse($dateFrom)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                Carbon::parse($dateTo)->setTime(7, 0, 0)    // إلى الساعة 7 صباحاً من اليوم التالي
            ])->sum('amount');

            // حساب إجمالي الرواتب للموظفين في الفترة المحددة
            $totalSalaries = \App\Models\Employee::where('is_active', true)->get()->sum(function($employee) use ($dateFrom, $dateTo) {
                return $employee->getTotalAmountForPeriod($dateFrom, $dateTo);
            });
        } else {
            $totalPurchases = \App\Models\Purchase::whereDate('purchase_date', $dateFrom)->sum('total_amount');
            $totalExpenses = \App\Models\Expense::whereBetween('created_at', [
                Carbon::parse($dateFrom)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                Carbon::parse($dateFrom)->addDay()->setTime(7, 0, 0) // إلى الساعة 7 صباحاً من اليوم التالي
            ])->sum('amount');

            // حساب إجمالي الرواتب للموظفين في اليوم المحدد
            $totalSalaries = \App\Models\Employee::where('is_active', true)->get()->sum(function($employee) use ($dateFrom) {
                return $employee->getTotalAmountForPeriod($dateFrom, $dateFrom);
            });
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
            'totalSalaries' => $totalSalaries,
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
