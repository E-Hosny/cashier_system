<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $aggregateHub = $user->hasRole('super admin') && BranchContext::id() === null;

        if ($aggregateHub) {
            $request->validate([
                'expense_branch' => ['sometimes', 'nullable', 'string', 'max:32'],
            ]);
        }

        $expenseBranch = $aggregateHub ? $this->normalizeExpenseBranchInput($request) : null;

        $query = Expense::query()->orderBy('created_at', 'desc');
        $this->applyExpenseBranchVisibility($query, $expenseBranch);

        if ($aggregateHub && $expenseBranch !== 'central') {
            $query->with(['branch:id,name']);
        }

        if ($request->filled('expense_date')) {
            $query->whereDate('expense_date', $request->expense_date);
        } elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereDate('expense_date', '>=', $request->from)
                ->whereDate('expense_date', '<=', $request->to);
        } elseif ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->from);
        } elseif ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->to);
        } else {
            $now = Carbon::now();
            $currentHour = $now->hour;

            if ($currentHour < 7) {
                $defaultDate = $now->copy()->subDay()->toDateString();
            } else {
                $defaultDate = $now->toDateString();
            }

            $query->whereDate('expense_date', $defaultDate);
        }

        $expenses = $query->get();

        $totalExpenses = $expenses->sum('amount');

        $defaultExpenseDate = null;
        if (! $request->filled('expense_date') && ! $request->filled('from') && ! $request->filled('to')) {
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
                'expense_branch' => $aggregateHub ? $expenseBranch : null,
            ],
            'expenseUi' => [
                'aggregateHub' => $aggregateHub,
                'canChooseCentral' => $user->hasRole('super admin') && BranchContext::id() !== null,
                'hubBranches' => $aggregateHub
                    ? Branch::query()->orderBy('name')->get(['id', 'name'])->values()->all()
                    : [],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'expense_scope' => 'sometimes|in:central,branch',
        ]);

        $user = Auth::user();
        $branchId = null;

        if ($user->hasRole('super admin')) {
            if (! BranchContext::id()) {
                $branchId = null;
            } else {
                $scope = $request->input('expense_scope', 'branch');
                $branchId = $scope === 'central' ? null : BranchContext::requireId();
            }
        } else {
            $branchId = BranchContext::requireId();
        }

        Expense::create(array_merge(
            $request->only('description', 'amount', 'expense_date'),
            ['branch_id' => $branchId]
        ));

        return redirect()->route('expenses.index', $this->preserveExpenseIndexQuery($request))
            ->with('success', 'تمت إضافة المصروف بنجاح.');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->ensureCanAccessExpense($expense);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
        ]);
        $expense->update($request->only('description', 'amount', 'expense_date'));

        return redirect()->route('expenses.index', $this->preserveExpenseIndexQuery($request))
            ->with('success', 'تم تعديل المصروف بنجاح.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->ensureCanAccessExpense($expense);
        $expense->delete();

        return redirect()->route('expenses.index', $this->preserveExpenseIndexQuery($request))
            ->with('success', 'تم حذف المصروف بنجاح.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function preserveExpenseIndexQuery(Request $request): array
    {
        return array_filter([
            'expense_date' => $request->input('preserve_expense_date'),
            'from' => $request->input('preserve_from'),
            'to' => $request->input('preserve_to'),
            'expense_branch' => $request->input('preserve_expense_branch'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function normalizeExpenseBranchInput(Request $request): string
    {
        $raw = $request->input('expense_branch');
        if ($raw === null && $request->filled('branch_scope')) {
            $legacy = $request->input('branch_scope');
            $raw = $legacy === 'central' ? 'central' : null;
        }
        $raw = $raw ?? 'central';
        if ($raw === 'all') {
            return 'central';
        }
        if ($raw === 'central') {
            return 'central';
        }
        if (is_numeric($raw)) {
            $id = (int) $raw;
            $tenantId = Auth::user()->tenant_id;
            $exists = Branch::query()->whereKey($id)->where('tenant_id', $tenantId)->exists();

            return $exists ? (string) $id : 'central';
        }

        return 'central';
    }

    protected function applyExpenseBranchVisibility($query, ?string $hubExpenseBranch): void
    {
        $user = Auth::user();
        if ($user->hasRole('super admin') && BranchContext::id() === null) {
            $scope = $hubExpenseBranch ?? 'central';
            if ($scope === 'central') {
                $query->whereNull('branch_id');

                return;
            }
            $query->where('branch_id', (int) $scope);

            return;
        }

        $bid = BranchContext::id();
        if ($bid !== null) {
            if ($user->hasRole('super admin')) {
                $query->where(function ($q) use ($bid) {
                    $q->where('branch_id', $bid)
                        ->orWhereNull('branch_id');
                });
            } else {
                $query->where('branch_id', $bid);
            }
        }
    }

    protected function ensureCanAccessExpense(Expense $expense): void
    {
        $user = Auth::user();
        if ($user->hasRole('super admin') && BranchContext::id() === null) {
            return;
        }

        $bid = BranchContext::id();
        abort_if($bid === null && ! $user->hasRole('super admin'), 403);
        if ($bid !== null) {
            abort_unless((int) $expense->branch_id === $bid, 403);
        }
    }
}
