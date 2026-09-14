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
        $summary = $employee->getFixedSalaryMonthSummary($yearMonth, $anchorDate);

        if ($amount > $summary['withdrawable_now'] + 0.0001) {
            $hideAmounts = $this->shouldHideSalaryAmountsFromViewer();

            if (! empty($summary['withdraw_limit_enabled']) && empty($summary['fully_unlocked'])) {
                $message = $hideAmounts
                    ? sprintf(
                        'قبل يوم فتح الراتب (%d من الشهر) يُسمح بسحب حتى %.0f%% فقط من الراتب.',
                        (int) ($summary['full_unlock_day'] ?? 29),
                        (float) ($summary['early_withdraw_percent'] ?? 0)
                    )
                    : sprintf(
                        'قبل يوم فتح الراتب (%d من الشهر) يُسمح بسحب حتى %.0f%% فقط من الراتب. المتاح الآن: %.2f (من أصل متبقي محاسبي %.2f).',
                        (int) ($summary['full_unlock_day'] ?? 29),
                        (float) ($summary['early_withdraw_percent'] ?? 0),
                        (float) $summary['withdrawable_now'],
                        (float) $summary['remaining']
                    );

                throw ValidationException::withMessages([
                    'amount' => $message,
                ]);
            }

            throw ValidationException::withMessages([
                'amount' => $hideAmounts
                    ? 'المبلغ المطلوب أكبر من المتاح للسحب الآن.'
                    : sprintf(
                        'المبلغ المطلوب (%.2f) أكبر من المتاح للسحب الآن (%.2f).',
                        $amount,
                        (float) $summary['withdrawable_now']
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

    /**
     * الكاشير يسجّل المسحوب دون الاطلاع على أرقام الراتب المتبقية.
     */
    private function shouldHideSalaryAmountsFromViewer(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return true;
        }

        if ($user->hasAnyRole(['admin', 'super admin'])) {
            return false;
        }

        return $user->hasRole('cashier');
    }
}
