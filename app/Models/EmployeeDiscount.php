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

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}
