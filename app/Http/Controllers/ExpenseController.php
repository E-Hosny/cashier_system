<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::orderBy('created_at', 'desc');

        // فلترة حسب يوم محدد - عرض جميع مصروفات اليوم المحدد
        if ($request->filled('expense_date')) {
            $query->whereDate('expense_date', $request->expense_date);
        }
        // فلترة حسب فترة زمنية
        elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('expense_date', [$request->from, $request->to]);
        }
        // فلترة من تاريخ فقط
        elseif ($request->filled('from')) {
            $query->where('expense_date', '>=', $request->from);
        }
        // فلترة إلى تاريخ فقط
        elseif ($request->filled('to')) {
            $query->where('expense_date', '<=', $request->to);
        }
        // افتراضياً: عرض مصروفات الفترة الحالية (من 7 صباحاً إلى 7 صباحاً للوم التالي)
        else {
            $now = Carbon::now();
            $currentHour = $now->hour;
            
            // تحديد التاريخ الصحيح بناءً على الوقت الحالي
            if ($currentHour < 7) {
                // قبل الساعة 7 صباحاً - نعرض مصروفات من 7 صباحاً اليوم السابق إلى 7 صباحاً اليوم الحالي
                $startDate = $now->copy()->subDay()->setTime(7, 0, 0);
                $endDate = $now->copy()->setTime(7, 0, 0);
                $defaultDate = $now->copy()->subDay()->toDateString();
            } else {
                // بعد الساعة 7 صباحاً - نعرض مصروفات من 7 صباحاً اليوم الحالي إلى 7 صباحاً للوم التالي
                $startDate = $now->copy()->setTime(7, 0, 0);
                $endDate = $now->copy()->addDay()->setTime(7, 0, 0);
                $defaultDate = $now->toDateString();
            }
            
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $expenses = $query->get();
        
        // حساب إجمالي المصروفات
        $totalExpenses = $expenses->sum('amount');
        
        // تحديد التاريخ الافتراضي للعرض في الواجهة
        $defaultExpenseDate = null;
        if (!$request->filled('expense_date') && !$request->filled('from') && !$request->filled('to')) {
            $now = Carbon::now();
            $currentHour = $now->hour;
            if ($currentHour < 7) {
                $defaultExpenseDate = $now->copy()->subDay()->toDateString();
            } else {
                $defaultExpenseDate = $now->toDateString();
            }
        }
        
        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'filters' => [
                'expense_date' => $request->expense_date ?? $defaultExpenseDate,
                'from' => $request->from,
                'to' => $request->to,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
        ]);
        Expense::create($request->only('description', 'amount', 'expense_date'));
        return redirect()->route('expenses.index')->with('success', 'تمت إضافة المصروف بنجاح.');
    }

    public function update(Request $request, Expense $expense)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
        ]);
        $expense->update($request->only('description', 'amount', 'expense_date'));
        return redirect()->route('expenses.index')->with('success', 'تم تعديل المصروف بنجاح.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'تم حذف المصروف بنجاح.');
    }
} 