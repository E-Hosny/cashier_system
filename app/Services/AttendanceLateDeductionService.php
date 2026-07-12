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
     * @return array{late_minutes: int|null, discount: EmployeeDiscount|null, rule: AttendanceDeductionRule|null, discount_created: bool, is_first_checkin: bool}
     */
    public function applyOnCheckin(Employee $employee, EmployeeAttendance $attendance): array
    {
        $checkinTime = Carbon::parse($attendance->checkin_time);
        $anchorDate = $this->businessDayAnchorForCheckin($checkinTime);
        $lateMinutes = $this->calculateLateMinutes($employee, $checkinTime);
        $isFirstCheckin = $this->isFirstCheckinOfBusinessDay($employee, $attendance, $anchorDate);

        // التأخير لا يُحتسب إلا لأول حضور في يوم العمل؛ التحضير المتكرر لا يُسجَّل كتأخير.
        $effectiveLateMinutes = $isFirstCheckin ? $lateMinutes : 0;

        $attendance->late_minutes = $effectiveLateMinutes ?? 0;
        $attendance->save();

        if ($lateMinutes === null || $lateMinutes <= 0) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null, 'discount_created' => false, 'is_first_checkin' => $isFirstCheckin];
        }

        if (! $employee->late_deductions_enabled) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null, 'discount_created' => false, 'is_first_checkin' => $isFirstCheckin];
        }

        if (! $isFirstCheckin) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null, 'discount_created' => false, 'is_first_checkin' => false];
        }

        if ($this->hasLateRuleDiscountForBusinessDay($employee, $anchorDate)) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null, 'discount_created' => false, 'is_first_checkin' => true];
        }

        $rule = $this->findMatchingRule($employee, $lateMinutes);
        if (! $rule) {
            return ['late_minutes' => $lateMinutes, 'discount' => null, 'rule' => null, 'discount_created' => false, 'is_first_checkin' => true];
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

        return ['late_minutes' => $lateMinutes, 'discount' => $discount, 'rule' => $rule, 'discount_created' => true, 'is_first_checkin' => true];
    }

    public function calculateLateMinutes(Employee $employee, Carbon $checkinTime): ?int
    {
        $schedule = $employee->getEffectiveWorkScheduleForCheckin($checkinTime);

        if (! $schedule['is_working'] || ! $schedule['expected_checkin_time']) {
            return null;
        }

        $timeParts = explode(':', (string) $schedule['expected_checkin_time']);
        $expected = $checkinTime->copy()->setTime(
            (int) ($timeParts[0] ?? 0),
            (int) ($timeParts[1] ?? 0),
            0
        );

        $grace = (int) ($schedule['grace_minutes'] ?? 0);
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

    private function isFirstCheckinOfBusinessDay(Employee $employee, EmployeeAttendance $attendance, string $anchorDate): bool
    {
        [$start, $end] = Employee::businessDayBoundsForAnchor($anchorDate);

        return ! $employee->attendanceRecords()
            ->where('id', '!=', $attendance->id)
            ->where('checkin_time', '>=', $start)
            ->where('checkin_time', '<', $end)
            ->exists();
    }

    private function hasLateRuleDiscountForBusinessDay(Employee $employee, string $anchorDate): bool
    {
        return EmployeeDiscount::query()
            ->where('employee_id', $employee->id)
            ->where('discount_date', $anchorDate)
            ->where('source', 'late_rule')
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
