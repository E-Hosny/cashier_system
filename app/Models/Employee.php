<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'name',
        'hourly_rate',
        'is_active',
        'phone',
        'position',
        'notes',
        'attendance_dependency_employee_id',
        'tenant_id',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    /**
     * علاقة مع سجلات الحضور والانصراف
     */
    public function attendanceRecords()
    {
        return $this->hasMany(EmployeeAttendance::class);
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
     * الموظف الذي يعتمد عليه هذا الموظف في السماح بالحضور.
     */
    public function attendanceDependencyEmployee()
    {
        return $this->belongsTo(Employee::class, 'attendance_dependency_employee_id');
    }

    /**
     * الموظفون الذين يعتمدون على هذا الموظف.
     */
    public function dependents()
    {
        return $this->hasMany(Employee::class, 'attendance_dependency_employee_id');
    }

    /**
     * إرجاع موظف يمنع تسجيل الحضور (سواء كان هو المعتمد عليه أو الموظف الحالي معتمد عليه).
     */
    public function getAttendanceBlockingEmployee(): ?Employee
    {
        $dependencyId = $this->attendance_dependency_employee_id;
        if ($dependencyId) {
            $dependencyEmployee = Employee::find($dependencyId);
            if ($dependencyEmployee && $dependencyEmployee->isCurrentlyPresent()) {
                return $dependencyEmployee;
            }
        }

        $dependentEmployee = Employee::where('attendance_dependency_employee_id', $this->id)
            ->get()
            ->first(fn (Employee $emp) => $emp->isCurrentlyPresent());
        if ($dependentEmployee) {
            return $dependentEmployee;
        }

        return null;
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
     * تاريخ بداية «يوم العمل» الحالي (من 7 ص إلى 7 ص): التقويم عند 7 ص يوم D يُعتبر يوم العمل D.
     */
    public static function businessDayAnchorFromNow(): string
    {
        $now = Carbon::now();

        return $now->hour < 7
            ? $now->copy()->subDay()->toDateString()
            : $now->toDateString();
    }

    /**
     * حدود الفترة [بداية، نهاية) ليوم عمل مرتبط بتاريخ الربط (نفس يوم التقويم عند الساعة 7 ص).
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    public static function businessDayBoundsForAnchor(string $anchorDate): array
    {
        $start = Carbon::parse($anchorDate)->setTime(7, 0, 0);
        $end = $start->copy()->addDay();

        return [$start, $end];
    }

    /**
     * نص توضيحي لفترة يوم العمل حسب تاريخ الربط
     */
    public static function periodTextForAnchorDate(string $anchorDate): string
    {
        $d = Carbon::parse($anchorDate)->format('Y-m-d');
        $next = Carbon::parse($anchorDate)->addDay()->format('Y-m-d');

        return "من الساعة 7:00 صباحاً {$d} إلى الساعة 7:00 صباحاً {$next}";
    }

    /**
     * إجمالي ساعات العمل ليوم عمل محدد (تاريخ الربط = نفس منطق الخصومات والراتب لليوم)
     */
    public function getHoursForBusinessDayAnchor(string $anchorDate): float
    {
        [$startDate, $endDate] = self::businessDayBoundsForAnchor($anchorDate);

        $attendances = $this->attendanceRecords()
            ->whereBetween('checkin_time', [$startDate, $endDate])
            ->get();

        $totalHours = 0;

        foreach ($attendances as $attendance) {
            $checkinTime = Carbon::parse($attendance->checkin_time);
            $checkoutTime = $attendance->checkout_time ?? Carbon::now();
            $checkoutTime = Carbon::parse($checkoutTime);

            if ($checkoutTime > $endDate) {
                $checkoutTime = $endDate;
            }

            $totalHours += $checkinTime->diffInHours($checkoutTime, true);
        }

        return (float) $totalHours;
    }

    /**
     * سجلات الحضور ليوم عمل محدد
     */
    public function getAttendanceRecordsForBusinessDayAnchor(string $anchorDate)
    {
        [$startDate, $endDate] = self::businessDayBoundsForAnchor($anchorDate);

        return $this->attendanceRecords()
            ->whereBetween('checkin_time', [$startDate, $endDate])
            ->orderBy('checkin_time', 'desc')
            ->get();
    }

    /**
     * خصومات يوم عمل محدد (حقل discount_date = تاريخ الربط)
     */
    public function getDiscountsForBusinessDayAnchor(string $anchorDate)
    {
        return $this->discounts()
            ->where('discount_date', Carbon::parse($anchorDate)->toDateString())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getDiscountTotalForBusinessDayAnchor(string $anchorDate): float
    {
        return (float) $this->getDiscountsForBusinessDayAnchor($anchorDate)->sum('amount');
    }

    /**
     * المبلغ بعد الخصومات ليوم عمل محدد
     */
    public function getAmountForBusinessDayAnchor(string $anchorDate): float
    {
        $hours = $this->getHoursForBusinessDayAnchor($anchorDate);
        $baseAmount = $hours * (float) $this->hourly_rate;
        $discountTotal = $this->getDiscountTotalForBusinessDayAnchor($anchorDate);

        return max(0, $baseAmount - $discountTotal);
    }

    /**
     * الحصول على إجمالي ساعات العمل لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function getTodayHours()
    {
        return $this->getHoursForBusinessDayAnchor(self::businessDayAnchorFromNow());
    }

    /**
     * الحصول على سجلات الحضور لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function getTodayAttendanceRecords()
    {
        return $this->getAttendanceRecordsForBusinessDayAnchor(self::businessDayAnchorFromNow());
    }

    /**
     * الحصول على إجمالي المبلغ المستحق لليوم الحالي (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     * مع خصم الخصومات اليومية
     */
    public function getTodayAmount()
    {
        return $this->getAmountForBusinessDayAnchor(self::businessDayAnchorFromNow());
    }

    /**
     * الحصول على خصومات اليوم الحالي
     */
    public function getTodayDiscounts()
    {
        return $this->getDiscountsForBusinessDayAnchor(self::businessDayAnchorFromNow());
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
        return self::periodTextForAnchorDate(self::businessDayAnchorFromNow());
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
     * مع خصم الخصومات
     */
    public function getAmountForPeriod($startDate, $endDate = null)
    {
        $hours = $this->getHoursForPeriod($startDate, $endDate);
        $baseAmount = $hours * $this->hourly_rate;
        
        // حساب الخصومات للفترة
        $start = Carbon::parse($startDate);
        $end = $endDate ? Carbon::parse($endDate) : $start;
        
        $discounts = $this->discounts()
            ->whereBetween('discount_date', [$start->toDateString(), $end->toDateString()])
            ->get();
        
        $discountTotal = $discounts->sum('amount');
        $finalAmount = max(0, $baseAmount - $discountTotal);
        
        return $finalAmount;
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
        return $this->getSalaryDeliveryForDate(self::businessDayAnchorFromNow());
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