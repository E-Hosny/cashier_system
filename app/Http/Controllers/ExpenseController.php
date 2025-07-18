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
        // افتراضياً: عرض مصروفات اليوم الحالي
        else {
            $query->whereDate('expense_date', Carbon::today());
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