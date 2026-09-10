<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFixedSalaryDebtWaiver extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const KIND_OPENING = 'opening';

    public const KIND_CLOSING = 'closing';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'year_month',
        'kind',
        'amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
