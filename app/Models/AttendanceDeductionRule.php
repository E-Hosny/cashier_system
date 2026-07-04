<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDeductionRule extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'name',
        'rule_type',
        'min_late_minutes',
        'max_late_minutes',
        'deduction_amount',
        'is_active',
        'sort_order',
        'tenant_id',
        'branch_id',
    ];

    protected $casts = [
        'min_late_minutes' => 'integer',
        'max_late_minutes' => 'integer',
        'deduction_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
        static::bootBelongsToBranch();
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'deduction_rule_employee');
    }

    public function discounts()
    {
        return $this->hasMany(EmployeeDiscount::class);
    }

    public function matchesLateMinutes(int $lateMinutes): bool
    {
        if ($this->rule_type === 'more_than') {
            return $lateMinutes > $this->min_late_minutes;
        }

        if ($lateMinutes < $this->min_late_minutes) {
            return false;
        }

        if ($this->max_late_minutes !== null && $lateMinutes > $this->max_late_minutes) {
            return false;
        }

        return true;
    }

    public function rangeLabel(): string
    {
        if ($this->rule_type === 'more_than') {
            return "أكثر من {$this->min_late_minutes} دقيقة";
        }

        if ($this->max_late_minutes === null) {
            return "من {$this->min_late_minutes} دقيقة فأكثر";
        }

        return "من {$this->min_late_minutes} إلى {$this->max_late_minutes} دقيقة";
    }

    public function ruleTypeLabel(): string
    {
        return $this->rule_type === 'more_than' ? 'أكثر من' : 'نطاق (من – إلى)';
    }
}
