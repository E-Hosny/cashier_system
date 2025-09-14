<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SalaryDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_date',
        'hours_worked',
        'hourly_rate',
        'total_amount',
        'status',
        'delivered_at',
        'delivered_by',
        'notes'
    ];

    protected $casts = [
        'salary_date' => 'date',
        'hours_worked' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivered_at' => 'datetime',
    ];

    /**
     * علاقة مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * علاقة مع المستخدم الذي قام بالتسليم
     */
    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    /**
     * تحديد حالة التسليم إلى "تم التسليم"
     */
    public function markAsDelivered($userId = null)
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => Carbon::now(),
            'delivered_by' => $userId ?? auth()->id()
        ]);
    }

    /**
     * التحقق من حالة التسليم
     */
    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    /**
     * نص حالة التسليم بالعربية
     */
    public function getStatusTextAttribute()
    {
        return $this->status === 'delivered' ? 'تم التسليم' : 'في الانتظار';
    }

    /**
     * تنسيق تاريخ التسليم للعرض
     */
    public function getDeliveredAtFormattedAttribute()
    {
        return $this->delivered_at ? $this->delivered_at->format('d/m/Y H:i') : null;
    }
}
