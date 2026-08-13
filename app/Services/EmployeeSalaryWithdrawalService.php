<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryWithdrawal;
use App\Models\Expense;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryWithdrawalService
{
    /**
     * تسجيل مسحوب من راتب ثابت وربطه بمصروف لنفس يوم العمل.
     */
    public function withdraw(Employee $employee, float $amount, ?string $notes = null, ?string $withdrawalDate = null): EmployeeSalaryWithdrawal
    {
        if (! $employee->isFixedSalary()) {
            throw ValidationException::withMessages([
                'employee' => 'المسحوبات متاحة فقط للموظفين ذوي الراتب الثابت.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'يجب أن يكون مبلغ السحب أكبر من صفر.',
            ]);
        }

        $anchorDate = $withdrawalDate
            ? Carbon::parse($withdrawalDate)->toDateString()
            : Employee::businessDayAnchorFromNow();

        $yearMonth = Carbon::parse($anchorDate)->format('Y-m');
        $summary = $employee->getFixedSalaryMonthSummary($yearMonth);

        if ($amount > $summary['remaining'] + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'المبلغ المطلوب (%.2f) أكبر من المتبقي من الراتب هذا الشهر (%.2f).',
                    $amount,
                    $summary['remaining']
                ),
            ]);
        }

        $branchId = BranchContext::id() ?? $employee->branch_id;

        return DB::transaction(function () use ($employee, $amount, $notes, $anchorDate, $yearMonth, $branchId) {
            $expense = Expense::create([
                'description' => 'مسحوب راتب - '.$employee->name,
                'amount' => $amount,
                'expense_date' => $anchorDate,
                'branch_id' => $branchId,
            ]);

            return EmployeeSalaryWithdrawal::create([
                'employee_id' => $employee->id,
                'expense_id' => $expense->id,
                'branch_id' => $branchId,
                'amount' => $amount,
                'withdrawal_date' => $anchorDate,
                'year_month' => $yearMonth,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
        });
    }

    /**
     * إلغاء مسحوب وحذف المصروف المرتبط به.
     */
    public function cancel(EmployeeSalaryWithdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $expenseId = $withdrawal->expense_id;
            $withdrawal->delete();

            if ($expenseId) {
                Expense::withoutGlobalScopes()
                    ->where('id', $expenseId)
                    ->delete();
            }
        });
    }
}
