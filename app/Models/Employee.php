<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'notes',
        'tenant_id'
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * علاقة مع المستأجر
     */
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * علاقة مع سجلات الحضور والانصراف
     */
    public function attendanceRecords()
    {
        return $this->hasMany(EmployeeAttendance::class);
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
     * علاقة مع سجلات تسليم الرواتب
     */
    public function salaryDeliveries()
    {
        return $this->hasMany(SalaryDelivery::class);
    }

    /**
     * علاقة مع الخصومات
     */
    public function discounts()
    {
        return $this->hasMany(EmployeeDiscount::class);
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
     * مع خصم الخصومات اليومية
     */
    public function getTodayAmount()
    {
        $hours = $this->getTodayHours();
        $baseAmount = $hours * $this->hourly_rate;
        $discountTotal = $this->getTodayDiscountTotal();
        $finalAmount = max(0, $baseAmount - $discountTotal); // التأكد من عدم وجود مبلغ سالب
        return $finalAmount;
    }

    /**
     * الحصول على خصومات اليوم الحالي
     */
    public function getTodayDiscounts()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الصحيح بناءً على الوقت الحالي (نفس منطق حساب الراتب)
        if ($currentHour < 7) {
            $targetDate = $now->copy()->subDay()->toDateString();
        } else {
            $targetDate = $now->copy()->toDateString();
        }
        
        return $this->discounts()
            ->where('discount_date', $targetDate)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * الحصول على إجمالي الخصومات لليوم الحالي
     */
    public function getTodayDiscountTotal()
    {
        $discounts = $this->getTodayDiscounts();
        return $discounts->sum('amount');
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

    /**
     * الحصول على إجمالي المبلغ المستحق لفترة محددة (مع مراعاة الفترات الزمنية 7 صباحاً - 7 صباحاً)
     */
    public function getTotalAmountForPeriod($startDate, $endDate = null)
    {
        if ($endDate === null) {
            $endDate = $startDate;
        }

        // إذا كان نفس اليوم، نستخدم منطق الفترة الزمنية (7 صباحاً إلى 7 صباحاً للوم التالي)
        if ($startDate === $endDate) {
            $startDateTime = Carbon::parse($startDate)->setTime(7, 0, 0);
            $endDateTime = Carbon::parse($startDate)->addDay()->setTime(7, 0, 0);
        } else {
            // إذا كانت فترة، نستخدم من 7 صباحاً اليوم الأول إلى 7 صباحاً اليوم الأخير
            $startDateTime = Carbon::parse($startDate)->setTime(7, 0, 0);
            $endDateTime = Carbon::parse($endDate)->addDay()->setTime(7, 0, 0);
        }

        // البحث عن سجلات الحضور في الفترة المحددة
        $attendances = $this->attendanceRecords()
            ->whereBetween('checkin_time', [$startDateTime, $endDateTime])
            ->whereNotNull('checkout_time')
            ->get();

        $totalHours = 0;

        foreach ($attendances as $attendance) {
            $checkinTime = Carbon::parse($attendance->checkin_time);
            $checkoutTime = Carbon::parse($attendance->checkout_time);

            // التأكد من أن وقت الانصراف لا يتجاوز نهاية الفترة
            if ($checkoutTime > $endDateTime) {
                $checkoutTime = $endDateTime;
            }

            $totalHours += $checkinTime->diffInHours($checkoutTime, true);
        }

        return $totalHours * $this->hourly_rate;
    }

    /**
     * الحصول على سجل تسليم الراتب لتاريخ محدد
     */
    public function getSalaryDeliveryForDate($date)
    {
        return $this->salaryDeliveries()->where('salary_date', $date)->first();
    }

    /**
     * الحصول على حالة تسليم راتب اليوم
     */
    public function getTodayDeliveryStatus()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الصحيح بناءً على الوقت الحالي
        if ($currentHour < 7) {
            $targetDate = $now->copy()->subDay()->toDateString();
        } else {
            $targetDate = $now->copy()->toDateString();
        }
        
        return $this->getSalaryDeliveryForDate($targetDate);
    }

    /**
     * إنشاء سجل تسليم راتب لتاريخ محدد
     */
    public function createSalaryDelivery($date, $hours, $amount)
    {
        return $this->salaryDeliveries()->create([
            'salary_date' => $date,
            'hours_worked' => $hours,
            'hourly_rate' => $this->hourly_rate,
            'total_amount' => $amount,
            'status' => 'pending'
        ]);
    }

    /**
     * إنشاء أو تحديث سجل تسليم راتب اليوم
     */
    public function createOrUpdateTodayDelivery()
    {
        $now = Carbon::now();
        $currentHour = $now->hour;
        
        // تحديد التاريخ الصحيح بناءً على الوقت الحالي
        if ($currentHour < 7) {
            $targetDate = $now->copy()->subDay()->toDateString();
        } else {
            $targetDate = $now->copy()->toDateString();
        }
        
        $hours = $this->getTodayHours();
        $amount = $this->getTodayAmount();
        
        // البحث عن سجل موجود
        $delivery = $this->getSalaryDeliveryForDate($targetDate);
        
        if ($delivery) {
            // تحديث السجل الموجود إذا لم يتم تسليمه بعد
            if (!$delivery->isDelivered()) {
                $delivery->update([
                    'hours_worked' => $hours,
                    'hourly_rate' => $this->hourly_rate,
                    'total_amount' => $amount
                ]);
            }
            return $delivery;
        } else {
            // إنشاء سجل جديد
            return $this->createSalaryDelivery($targetDate, $hours, $amount);
        }
    }

    /**
     * الحصول على إجمالي المبالغ المسلمة لفترة محددة
     */
    public function getDeliveredAmountForPeriod($startDate, $endDate = null)
    {
        if ($endDate === null) {
            $endDate = $startDate;
        }

        return $this->salaryDeliveries()
            ->where('status', 'delivered')
            ->whereBetween('salary_date', [$startDate, $endDate])
            ->sum('total_amount');
    }

    /**
     * الحصول على إجمالي المبالغ غير المسلمة لفترة محددة
     */
    public function getPendingAmountForPeriod($startDate, $endDate = null)
    {
        if ($endDate === null) {
            $endDate = $startDate;
        }

        return $this->salaryDeliveries()
            ->where('status', 'pending')
            ->whereBetween('salary_date', [$startDate, $endDate])
            ->sum('total_amount');
    }
} 