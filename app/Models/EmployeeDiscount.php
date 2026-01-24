<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EmployeeDiscount extends Model
{
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
     * علاقة مع المستأجر
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

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
        static::addGlobalScope('tenant', function (Builder $query) {
            if (auth()->check()) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}
