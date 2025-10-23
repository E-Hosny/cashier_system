<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class EmployeeAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'checkin_time',
        'checkout_time',
        'total_hours',
        'total_amount',
        'notes',
        'tenant_id'
    ];

    protected $casts = [
        'checkin_time' => 'datetime',
        'checkout_time' => 'datetime',
        'total_hours' => 'decimal:2',
        'total_amount' => 'decimal:2',
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

    /**
     * حساب الساعات والمبلغ عند الانصراف
     */
    public function calculateHoursAndAmount()
    {
        if ($this->checkout_time) {
            $checkin = Carbon::parse($this->checkin_time);
            $checkout = Carbon::parse($this->checkout_time);
            
            $this->total_hours = $checkin->diffInHours($checkout, true);
            $this->total_amount = $this->total_hours * $this->employee->hourly_rate;
            
            $this->save();
        }
    }

    /**
     * الحصول على الساعات المتبقية (إذا كان الحضور مفتوح)
     */
    public function getCurrentHours()
    {
        if (!$this->checkout_time) {
            $checkin = Carbon::parse($this->checkin_time);
            $now = Carbon::now();
            return $checkin->diffInHours($now, true);
        }
        
        return $this->total_hours;
    }

    /**
     * الحصول على المبلغ الحالي (إذا كان الحضور مفتوح)
     */
    public function getCurrentAmount()
    {
        $hours = $this->getCurrentHours();
        return $hours * $this->employee->hourly_rate;
    }

    /**
     * تنسيق وقت الحضور للعرض
     */
    public function getFormattedCheckinTime()
    {
        return Carbon::parse($this->checkin_time)->format('H:i');
    }

    /**
     * تنسيق وقت الانصراف للعرض
     */
    public function getFormattedCheckoutTime()
    {
        if ($this->checkout_time) {
            return Carbon::parse($this->checkout_time)->format('H:i');
        }
        return '-';
    }

    /**
     * تنسيق التاريخ للعرض
     */
    public function getFormattedDate()
    {
        return Carbon::parse($this->checkin_time)->format('Y-m-d');
    }
} 