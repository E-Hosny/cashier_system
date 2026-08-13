<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SalaryDelivery extends Model
{
    use BelongsToTenant;
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
        'notes',
        'tenant_id',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

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
     * حدود يوم العمل على عمود delivered_at (7 ص → 7 ص).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public static function deliveredAtBoundsForAnchor(string $anchorDate): array
    {
        $start = Carbon::parse($anchorDate)->setTime(7, 0, 0);
        $end = $start->copy()->addDay();

        return [$start, $end];
    }

    /**
     * تسليمات تمت فعلياً خلال يوم عمل معيّن (حسب وقت الضغط على تسليم).
     */
    public function scopeDeliveredDuringBusinessDay($query, string $anchorDate)
    {
        [$start, $end] = self::deliveredAtBoundsForAnchor($anchorDate);

        return $query
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $start)
            ->where('delivered_at', '<', $end);
    }

    /**
     * تسليمات تمت فعلياً خلال فترة أيام عمل (من تاريخ إلى تاريخ شامل).
     */
    public function scopeDeliveredDuringBusinessDayRange($query, string $dateFrom, string $dateTo)
    {
        $start = Carbon::parse($dateFrom)->setTime(7, 0, 0);
        $end = Carbon::parse($dateTo)->addDay()->setTime(7, 0, 0);

        return $query
            ->where('status', 'delivered')
            ->where('delivered_at', '>=', $start)
            ->where('delivered_at', '<', $end);
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
