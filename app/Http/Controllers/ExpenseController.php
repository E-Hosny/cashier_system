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

        // فلترة حسب يوم محدد - من الساعة 7 صباحاً إلى الساعة 7 صباحاً من اليوم التالي
        if ($request->filled('expense_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->expense_date)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                Carbon::parse($request->expense_date)->addDay()->setTime(7, 0, 0) // إلى الساعة 7 صباحاً من اليوم التالي
            ]);
        }
        // فلترة حسب فترة زمنية
        elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from)->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                Carbon::parse($request->to)->setTime(7, 0, 0)    // إلى الساعة 7 صباحاً من اليوم التالي
            ]);
        }
        // فلترة من تاريخ فقط
        elseif ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->from)->setTime(7, 0, 0));
        }
        // فلترة إلى تاريخ فقط
        elseif ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->to)->setTime(7, 0, 0));
        }
        // افتراضياً: عرض مصروفات اليوم الحالي من الساعة 7 صباحاً إلى الساعة 7 صباحاً من الغد
        else {
            $query->whereBetween('created_at', [
                Carbon::today()->setTime(7, 0, 0), // بداية من الساعة 7 صباحاً
                Carbon::tomorrow()->setTime(7, 0, 0) // إلى الساعة 7 صباحاً من الغد
            ]);
        }

        $expenses = $query->get();
        
        // حساب إجمالي المصروفات
        $totalExpenses = $expenses->sum('amount');
        
        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'filters' => [
                'expense_date' => $request->expense_date,
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