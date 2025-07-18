<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hourly_rate',
        'is_active',
        'phone',
        'position',
        'notes'
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * علاقة مع سجلات الحضور والانصراف
     */
    public function attendanceRecords()
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    /**
     * الحصول على سجل الحضور الحالي (إذا كان موجوداً)
     */
    public function getCurrentAttendance()
    {
        // البحث عن أي سجل حضور مفتوح (بدون وقت انصراف)
        return $this->attendanceRecords()
            ->whereNull('checkout_time')
            ->orderBy('checkin_time', 'desc')
            ->first();
    }

    /**
     * التحقق من وجود سجل حضور مفتوح
     */
    public function isCurrentlyPresent()
    {
        return $this->getCurrentAttendance() !== null;
    }

    /**
     * الحصول على إجمالي ساعات العمل لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function getTodayHours()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الصحيح بناءً على الوقت الحالي
        if ($currentHour < 7) {
            // قبل الساعة 7 صباحاً - نحسب من 7 صباحاً اليوم السابق إلى 7 صباحاً اليوم الحالي
            $startDate = $now->copy()->subDay()->setTime(7, 0, 0);
            $endDate = $now->copy()->setTime(7, 0, 0);
        } else {
            // بعد الساعة 7 صباحاً - نحسب من 7 صباحاً اليوم الحالي إلى 7 صباحاً للوم التالي
            $startDate = $now->copy()->setTime(7, 0, 0);
            $endDate = $now->copy()->addDay()->setTime(7, 0, 0);
        }
        
        // البحث عن سجلات الحضور في الفترة المحددة
        $attendances = $this->attendanceRecords()
            ->whereBetween('checkin_time', [$startDate, $endDate])
            ->get();
        
        $totalHours = 0;
        
        foreach ($attendances as $attendance) {
            $checkinTime = Carbon::parse($attendance->checkin_time);
            $checkoutTime = $attendance->checkout_time ?? Carbon::now();
            $checkoutTime = Carbon::parse($checkoutTime);
            
            // التأكد من أن وقت الانصراف لا يتجاوز نهاية الفترة
            if ($checkoutTime > $endDate) {
                $checkoutTime = $endDate;
            }
            
            $totalHours += $checkinTime->diffInHours($checkoutTime, true);
        }
        
        return $totalHours;
    }

    /**
     * الحصول على سجلات الحضور لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function getTodayAttendanceRecords()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الصحيح بناءً على الوقت الحالي
        if ($currentHour < 7) {
            // قبل الساعة 7 صباحاً - نحسب من 7 صباحاً اليوم السابق إلى 7 صباحاً اليوم الحالي
            $startDate = $now->copy()->subDay()->setTime(7, 0, 0);
            $endDate = $now->copy()->setTime(7, 0, 0);
        } else {
            // بعد الساعة 7 صباحاً - نحسب من 7 صباحاً اليوم الحالي إلى 7 صباحاً للوم التالي
            $startDate = $now->copy()->setTime(7, 0, 0);
            $endDate = $now->copy()->addDay()->setTime(7, 0, 0);
        }
        
        return $this->attendanceRecords()
            ->whereBetween('checkin_time', [$startDate, $endDate])
            ->orderBy('checkin_time', 'desc')
            ->get();
    }

    /**
     * الحصول على إجمالي المبلغ المستحق لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function getTodayAmount()
    {
        $hours = $this->getTodayHours();
        return $hours * $this->hourly_rate;
    }

    /**
     * الحصول على الفترة الزمنية الحالية للعرض
     */
    public function getCurrentPeriodText()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        if ($currentHour < 7) {
            // قبل الساعة 7 صباحاً
            $startDate = $now->copy()->subDay()->format('Y-m-d');
            $endDate = $now->copy()->format('Y-m-d');
            return "من الساعة 7:00 صباحاً {$startDate} إلى الساعة 7:00 صباحاً {$endDate}";
        } else {
            // بعد الساعة 7 صباحاً
            $startDate = $now->copy()->format('Y-m-d');
            $endDate = $now->copy()->addDay()->format('Y-m-d');
            return "من الساعة 7:00 صباحاً {$startDate} إلى الساعة 7:00 صباحاً {$endDate}";
        }
    }

    /**
     * الحصول على إجمالي ساعات العمل لفترة محددة
     */
    public function getHoursForPeriod($startDate, $endDate = null)
    {
        $query = $this->attendanceRecords()
            ->whereNotNull('checkout_time')
            ->whereBetween('checkin_time', [$startDate, $endDate ?? $startDate]);

        return $query->get()->sum(function ($record) {
            $checkin = Carbon::parse($record->checkin_time);
            $checkout = Carbon::parse($record->checkout_time);
            return $checkin->diffInHours($checkout, true);
        });
    }

    /**
     * الحصول على إجمالي المبلغ المستحق لفترة محددة
     */
    public function getAmountForPeriod($startDate, $endDate = null)
    {
        $hours = $this->getHoursForPeriod($startDate, $endDate);
        return $hours * $this->hourly_rate;
    }
} 