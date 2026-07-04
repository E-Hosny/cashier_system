<?php

namespace App\Services;

use App\Models\AttendanceDeductionRule;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeDiscount;
use Carbon\Carbon;

class AttendanceLateDeductionService
{
    /**
     * @return array{late_minutes: int|null, discount: EmployeeDiscount|null, rule: AttendanceDeductionRule|null}
     */
    public function applyOnCheckin(Employee $employee, EmployeeAttendance $attendance): array
    {
        $checkinTime = Carbon::parse($attendance->checkin_time);
        $anchorDate = $this->businessDayAnchorForCheckin($checkinTime);
        $lateMinutes = $this->calculateLateMinutes($employee, $checkinTime);

        if ($lateMinutes !== null) {
            $attendance->late_minutes = $lateMinutes;
            $attendance->save();
        }

        if ($lateMinutes === null || $lateMinutes <= 0) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null];
        }

        if (! $employee->late_deductions_enabled) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null];
        }

        if (! $this->isFirstCheckinOfBusinessDay($employee, $checkinTime, $anchorDate)) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null];
        }

        $rule = $this->findMatchingRule($employee, $lateMinutes);
        if (! $rule) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null];
        }

        $existing = EmployeeDiscount::query()
            ->where('employee_id', $employee->id)
            ->where('discount_date', $anchorDate)
            ->where('source', 'late_rule')
            ->where('attendance_deduction_rule_id', $rule->id)
            ->first();

        if ($existing) {
            return ['late_minutes' => $lateMinutes, 'discount' => $existing, 'rule' => $rule];
        }

        $reason = $this->buildReason($rule, $lateMinutes);

        $discount = EmployeeDiscount::create([
            'employee_id' => $employee->id,
            'discount_date' => $anchorDate,
            'amount' => $rule->deduction_amount,
            'reason' => $reason,
            'source' => 'late_rule',
            'attendance_deduction_rule_id' => $rule->id,
            'employee_attendance_id' => $attendance->id,
            'created_by' => auth()->id(),
        ]);

        return ['late_minutes' => $lateMinutes, 'discount' => $discount, 'rule' => $rule];
    }

    public function calculateLateMinutes(Employee $employee, Carbon $checkinTime): ?int
    {
        if (! $employee->expected_checkin_time) {
            return null;
        }

        $timeParts = explode(':', (string) $employee->expected_checkin_time);
        $expected = $checkinTime->copy()->setTime(
            (int) ($timeParts[0] ?? 0),
            (int) ($timeParts[1] ?? 0),
            0
        );

        $grace = (int) ($employee->grace_minutes ?? 0);
        $expected->addMinutes($grace);

        if ($checkinTime->lte($expected)) {
            return 0;
        }

        return (int) $expected->diffInMinutes($checkinTime);
    }

    public function findMatchingRule(Employee $employee, int $lateMinutes): ?AttendanceDeductionRule
    {
        return AttendanceDeductionRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('min_late_minutes')
            ->get()
            ->first(fn (AttendanceDeductionRule $rule) => $rule->matchesLateMinutes($lateMinutes));
    }

    private function isFirstCheckinOfBusinessDay(Employee $employee, Carbon $checkinTime, string $anchorDate): bool
    {
        [$start, $end] = Employee::businessDayBoundsForAnchor($anchorDate);

        return ! $employee->attendanceRecords()
            ->whereBetween('checkin_time', [$start, $end])
            ->where('checkin_time', '<', $checkinTime)
            ->exists();
    }

    private function businessDayAnchorForCheckin(Carbon $checkinTime): string
    {
        return $checkinTime->hour < 7
            ? $checkinTime->copy()->subDay()->toDateString()
            : $checkinTime->toDateString();
    }

    private function buildReason(AttendanceDeductionRule $rule, int $lateMinutes): string
    {
        $label = $rule->name ?: 'خصم تأخير';
        $range = $rule->rangeLabel();

        return "{$label} — تأخير {$lateMinutes} د ({$range})";
    }
}
