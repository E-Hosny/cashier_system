<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmployeeDiscount extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'discount_date',
        'amount',
        'reason',
        'source',
        'attendance_deduction_rule_id',
        'employee_attendance_id',
        'created_by',
        'tenant_id'
    ];

    protected $casts = [
        'discount_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * علاقة مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * علاقة مع المستخدم الذي أضاف الخصم
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceDeductionRule()
    {
        return $this->belongsTo(AttendanceDeductionRule::class);
    }

    public function employeeAttendance()
    {
        return $this->belongsTo(EmployeeAttendance::class);
    }

    public function isAutomatic(): bool
    {
        return in_array($this->source, ['late_rule', 'absence_vacation'], true);
    }

    public const SOURCE_LATE_RULE = 'late_rule';

    public const SOURCE_ABSENCE_VACATION = 'absence_vacation';

    public const SOURCE_MANUAL = 'manual';

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}
