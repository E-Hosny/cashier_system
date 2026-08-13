<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalaryDelivery;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SalesReportController extends Controller
{
    public function index(Request $request)
    {
        $now = Carbon::now();
        $currentHour = $now->hour;

        if ($currentHour < 7) {
            $defaultDate = $now->copy()->subDay()->toDateString();
        } else {
            $defaultDate = $now->toDateString();
        }

        $dateFrom = $request->input('date_from', $defaultDate);
        $dateTo = $request->input('date_to', null);
        $categoryId = $request->input('category_id', null);
        $productId = $request->input('product_id', null);

        $user = Auth::user();
        $salesReportHub = $user->hasRole('super admin') && BranchContext::id() === null;
        $hubReportBranchId = $salesReportHub ? $this->normalizeReportBranchId($request) : null;
        $aggregateAllBranches = $salesReportHub && $hubReportBranchId === null;

        $salesQuery = OrderItem::whereHas('order', function ($query) use ($dateFrom, $dateTo, $hubReportBranchId) {
            $query->where('status', 'completed');
            if ($dateTo) {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->setTime(7, 0, 0),
                    Carbon::parse($dateTo)->setTime(7, 0, 0),
                ]);
            } else {
                $query->whereBetween('created_at', [
                    Carbon::parse($dateFrom)->setTime(7, 0, 0),
                    Carbon::parse($dateFrom)->addDay()->setTime(7, 0, 0),
                ]);
            }
            if ($hubReportBranchId !== null) {
                $query->where('branch_id', $hubReportBranchId);
            }
        })
            ->with(['product.category']);

        if ($categoryId) {
            $salesQuery->whereHas('product', function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            });
        }

        if ($productId) {
            $salesQuery->where('product_id', $productId);
        }

        $sales = $salesQuery
            ->selectRaw('product_id, size, SUM(quantity) as total_quantity, AVG(price) as unit_price, SUM(quantity * price) as total_price')
            ->groupBy('product_id', 'size')
            ->get();

        if ($dateTo) {
            $totalPurchases = \App\Models\Purchase::whereBetween('purchase_date', [
                Carbon::parse($dateFrom)->toDateString(),
                Carbon::parse($dateTo)->toDateString(),
            ])->sum('total_amount');

            $totalExpenses = $this->expensesSumForReport(
                $dateFrom,
                $dateTo,
                $salesReportHub,
                $hubReportBranchId,
            );

            $totalSalaries = $this->sumDeliveredSalariesForReport($dateFrom, $dateTo, $hubReportBranchId);
        } else {
            $totalPurchases = \App\Models\Purchase::whereDate('purchase_date', $dateFrom)->sum('total_amount');

            $totalExpenses = $this->expensesSumForReport(
                $dateFrom,
                null,
                $salesReportHub,
                $hubReportBranchId,
            );

            $totalSalaries = $this->sumDeliveredSalariesForReport($dateFrom, null, $hubReportBranchId);
        }

        $totalSales = $sales->sum('total_price');

        $branchSalesSummary = [];
        $branchExpenseSummary = [];
        $branchSalarySummary = [];
        if ($aggregateAllBranches) {
            $branchSalesSummary = $this->branchSalesTotals($dateFrom, $dateTo, $categoryId, $productId);
            if ($dateTo) {
                $branchExpenseSummary = $this->branchExpenseTotals($dateFrom, $dateTo);
                $branchSalarySummary = $this->branchSalaryTotals($dateFrom, $dateTo);
            } else {
                $branchExpenseSummary = $this->branchExpenseTotals($dateFrom, null);
                $branchSalarySummary = $this->branchSalaryTotals($dateFrom, null);
            }
        }

        $categories = Category::forProducts()
            ->orderBy('name')
            ->get();
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
            'salesReportHub' => $salesReportHub,
            'hub_report_branch_id' => $hubReportBranchId,
            'reportBranches' => $salesReportHub
                ? Branch::query()->orderBy('name')->get(['id', 'name'])->values()->all()
                : [],
            'aggregateAllBranches' => $aggregateAllBranches,
            'branchSalesSummary' => $branchSalesSummary,
            'branchExpenseSummary' => $branchExpenseSummary,
            'branchSalarySummary' => $branchSalarySummary,
        ]);
    }

    protected function normalizeReportBranchId(Request $request): ?int
    {
        if (! $request->filled('report_branch_id')) {
            return null;
        }

        $id = (int) $request->input('report_branch_id');
        if ($id <= 0) {
            return null;
        }

        $tenantId = Auth::user()->tenant_id;
        $exists = Branch::query()->whereKey($id)->where('tenant_id', $tenantId)->exists();

        return $exists ? $id : null;
    }

    protected function expensesSumForReport(string $dateFrom, ?string $dateTo, bool $salesReportHub, ?int $hubReportBranchId): float
    {
        $query = Expense::query();
        $this->applySalesReportExpenseBranchScope($query, $salesReportHub, $hubReportBranchId);

        if ($dateTo) {
            $query->whereDate('expense_date', '>=', $dateFrom)
                ->whereDate('expense_date', '<=', $dateTo);
        } else {
            $query->whereDate('expense_date', $dateFrom);
        }

        return (float) $query->sum('amount');
    }

    /**
     * نطاق المصروفات في التقرير: عند تحديد فرع نُضمّ المصروفات المركزية (branch_id null)
     * لأنها تُسجَّل غالباً من المحور المركزي وتُطبَّق على كل الفروع.
     */
    protected function applySalesReportExpenseBranchScope($query, bool $salesReportHub, ?int $hubReportBranchId): void
    {
        $branchId = null;

        if ($salesReportHub) {
            $branchId = $hubReportBranchId;
        } elseif (($bid = BranchContext::id()) !== null) {
            $branchId = $bid;
        }

        if ($branchId === null) {
            return;
        }

        $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
                ->orWhereNull('branch_id');
        });
    }

    protected function sumDeliveredSalariesForReport(string $dateFrom, ?string $dateTo, ?int $hubReportBranchId): float
    {
        $query = SalaryDelivery::query()
            ->where('status', 'delivered')
            ->whereHas('employee', function ($q) use ($hubReportBranchId) {
                $q->where('is_active', true);
                if ($hubReportBranchId !== null) {
                    $q->where('branch_id', $hubReportBranchId);
                }
            });

        // حسب وقت التسليم الفعلي (delivered_at) وليس تاريخ يوم العمل المستحق
        if ($dateTo) {
            $query->deliveredDuringBusinessDayRange($dateFrom, $dateTo);
        } else {
            $query->deliveredDuringBusinessDay($dateFrom);
        }

        return (float) $query->sum('total_amount');
    }

    /**
     * توزيع المبيعات حسب الفرع — نفس منطق جدول المنتجات (مجموع بنود الطلبات)، وليس orders.total، لتطابق إجمالي التقرير.
     */
    protected function branchSalesTotals(string $dateFrom, ?string $dateTo, ?string $categoryId, ?string $productId): array
    {
        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed');

        if ($dateTo) {
            $query->whereBetween('orders.created_at', [
                Carbon::parse($dateFrom)->setTime(7, 0, 0),
                Carbon::parse($dateTo)->setTime(7, 0, 0),
            ]);
        } else {
            $query->whereBetween('orders.created_at', [
                Carbon::parse($dateFrom)->setTime(7, 0, 0),
                Carbon::parse($dateFrom)->copy()->addDay()->setTime(7, 0, 0),
            ]);
        }

        if ($categoryId) {
            $query->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('products.category_id', $categoryId);
        }

        if ($productId) {
            $query->where('order_items.product_id', $productId);
        }

        $rows = $query
            ->selectRaw('orders.branch_id, SUM(order_items.quantity * order_items.price) as total_sales')
            ->groupBy('orders.branch_id')
            ->get();

        $branchIds = $rows->pluck('branch_id')->filter();
        $names = Branch::whereIn('id', $branchIds)->pluck('name', 'id');

        return $rows->map(fn ($row) => [
            'branch_id' => $row->branch_id,
            'branch_name' => $row->branch_id === null
                ? 'بدون فرع'
                : ($names[$row->branch_id] ?? ('فرع #'.$row->branch_id)),
            'total_sales' => (float) $row->total_sales,
        ])->values()->all();
    }

    /**
     * توزيع المصروفات حسب الفرع (حسب تاريخ المصروف expense_date).
     *
     * @return array<int, array{branch_id: int|null, branch_name: string, total_expenses: float}>
     */
    protected function branchExpenseTotals(string $dateFrom, ?string $dateTo): array
    {
        $query = Expense::query();

        if ($dateTo) {
            $query->whereDate('expense_date', '>=', $dateFrom)
                ->whereDate('expense_date', '<=', $dateTo);
        } else {
            $query->whereDate('expense_date', $dateFrom);
        }

        $rows = $query
            ->selectRaw('branch_id, SUM(amount) as total_expenses')
            ->groupBy('branch_id')
            ->get();

        $branchIds = $rows->pluck('branch_id')->filter();
        $names = Branch::whereIn('id', $branchIds)->pluck('name', 'id');

        $mapped = $rows->map(fn ($row) => [
            'branch_id' => $row->branch_id,
            'branch_name' => $row->branch_id === null
                ? 'مركزي'
                : ($names[$row->branch_id] ?? ('فرع #'.$row->branch_id)),
            'total_expenses' => (float) $row->total_expenses,
        ]);

        return $this->sortBranchSummaryRows($mapped);
    }

    /**
     * توزيع الرواتب المسلمة حسب فرع الموظف (نفس منطق إجمالي الرواتب في التقرير).
     *
     * @return array<int, array{branch_id: int|null, branch_name: string, total_salaries: float}>
     */
    protected function branchSalaryTotals(string $dateFrom, ?string $dateTo): array
    {
        $query = SalaryDelivery::query()
            ->where('salary_deliveries.status', 'delivered')
            ->join('employees', 'salary_deliveries.employee_id', '=', 'employees.id')
            ->where('employees.is_active', true);

        // حسب وقت التسليم الفعلي (delivered_at)
        if ($dateTo) {
            $start = Carbon::parse($dateFrom)->setTime(7, 0, 0);
            $end = Carbon::parse($dateTo)->addDay()->setTime(7, 0, 0);
            $query->where('salary_deliveries.delivered_at', '>=', $start)
                ->where('salary_deliveries.delivered_at', '<', $end);
        } else {
            $start = Carbon::parse($dateFrom)->setTime(7, 0, 0);
            $end = $start->copy()->addDay();
            $query->where('salary_deliveries.delivered_at', '>=', $start)
                ->where('salary_deliveries.delivered_at', '<', $end);
        }

        $rows = $query
            ->selectRaw('employees.branch_id, SUM(salary_deliveries.total_amount) as total_salaries')
            ->groupBy('employees.branch_id')
            ->get();

        $branchIds = $rows->pluck('branch_id')->filter();
        $names = Branch::whereIn('id', $branchIds)->pluck('name', 'id');

        $mapped = $rows->map(function ($row) use ($names) {
            $bid = $row->branch_id;

            return [
                'branch_id' => $bid,
                'branch_name' => $bid === null
                    ? 'بدون فرع'
                    : ($names[$bid] ?? ('فرع #'.$bid)),
                'total_salaries' => (float) $row->total_salaries,
            ];
        });

        return $this->sortBranchSummaryRows($mapped);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{branch_name: string}>  $rows
     * @return array<int, mixed>
     */
    protected function sortBranchSummaryRows(Collection $rows): array
    {
        return $rows->sortBy(function (array $row) {
            $bid = $row['branch_id'] ?? null;

            return [(int) ($bid !== null), $row['branch_name']];
        })->values()->all();
    }
}
