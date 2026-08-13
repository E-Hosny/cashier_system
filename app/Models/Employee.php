<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    public const SALARY_TYPE_HOURLY = 'hourly';
    public const SALARY_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'name',
        'hourly_rate',
        'salary_type',
        'fixed_salary',
        'is_active',
        'phone',
        'position',
        'notes',
        'expected_checkin_time',
        'expected_checkout_time',
        'grace_minutes',
        'late_deductions_enabled',
        'attendance_dependency_employee_id',
        'attendance_group_id',
        'attendance_group_code',
        'attendance_group_max_present',
        'tenant_id',
        'branch_id',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'fixed_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'grace_minutes' => 'integer',
        'late_deductions_enabled' => 'boolean',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
        static::bootBelongsToBranch();
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
     * مسحوبات الراتب الثابت
     */
    public function salaryWithdrawals()
    {
        return $this->hasMany(EmployeeSalaryWithdrawal::class);
    }

    public function isFixedSalary(): bool
    {
        return ($this->salary_type ?? self::SALARY_TYPE_HOURLY) === self::SALARY_TYPE_FIXED;
    }

    public function isHourlySalary(): bool
    {
        return ! $this->isFixedSalary();
    }

    /**
     * ملخص راتب الشهر للموظفين ذوي الراتب الثابت.
     *
     * @return array{
     *   year_month: string,
     *   fixed_salary: float,
     *   withdrawals_total: float,
     *   discounts_total: float,
     *   remaining: float,
     *   withdrawals_count: int
     * }
     */
    public function getFixedSalaryMonthSummary(?string $yearMonth = null): array
    {
        $yearMonth = $yearMonth ?: Carbon::now()->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $yearMonth));
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $withdrawalsTotal = (float) $this->salaryWithdrawals()
            ->where('year_month', $yearMonth)
            ->sum('amount');

        $withdrawalsCount = (int) $this->salaryWithdrawals()
            ->where('year_month', $yearMonth)
            ->count();

        $discountsTotal = (float) $this->discounts()
            ->whereDate('discount_date', '>=', $start->toDateString())
            ->whereDate('discount_date', '<=', $end->toDateString())
            ->sum('amount');

        $fixedSalary = (float) ($this->fixed_salary ?? 0);
        $remaining = max(0, $fixedSalary - $discountsTotal - $withdrawalsTotal);

        return [
            'year_month' => $yearMonth,
            'fixed_salary' => round($fixedSalary, 2),
            'withdrawals_total' => round($withdrawalsTotal, 2),
            'discounts_total' => round($discountsTotal, 2),
            'remaining' => round($remaining, 2),
            'withdrawals_count' => $withdrawalsCount,
        ];
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

    public function attendanceGroup()
    {
        return $this->belongsTo(AttendanceGroup::class, 'attendance_group_id');
    }

    public function attendanceDeductionRules()
    {
        return $this->belongsToMany(AttendanceDeductionRule::class, 'deduction_rule_employee');
    }

    public function workSchedules()
    {
        return $this->hasMany(EmployeeWorkSchedule::class)->orderBy('day_of_week');
    }

    public function usesWeeklySchedule(): bool
    {
        return $this->relationLoaded('workSchedules')
            ? $this->workSchedules->isNotEmpty()
            : $this->workSchedules()->exists();
    }

    /**
     * @return array{is_working: bool, expected_checkin_time: string|null, expected_checkout_time: string|null, grace_minutes: int}
     */
    public function getEffectiveWorkScheduleForCheckin(Carbon $checkinTime): array
    {
        $dayOfWeek = $checkinTime->dayOfWeek;
        $weekly = $this->workSchedules->firstWhere('day_of_week', $dayOfWeek);

        if ($this->usesWeeklySchedule()) {
            if (! $weekly || ! $weekly->is_working) {
                return [
                    'is_working' => false,
                    'expected_checkin_time' => null,
                    'expected_checkout_time' => null,
                    'grace_minutes' => 0,
                ];
            }

            return [
                'is_working' => true,
                'expected_checkin_time' => $weekly->formattedExpectedCheckinTime()
                    ?? $this->formattedExpectedCheckinTime(),
                'expected_checkout_time' => $weekly->formattedExpectedCheckoutTime()
                    ?? $this->formattedExpectedCheckoutTime(),
                'grace_minutes' => $weekly->grace_minutes ?? (int) ($this->grace_minutes ?? 0),
            ];
        }

        return [
            'is_working' => (bool) $this->expected_checkin_time,
            'expected_checkin_time' => $this->formattedExpectedCheckinTime(),
            'expected_checkout_time' => $this->formattedExpectedCheckoutTime(),
            'grace_minutes' => (int) ($this->grace_minutes ?? 0),
        ];
    }

    /**
     * @return array<int, array{day_of_week: int, label: string, is_working: bool, expected_checkin_time: string, expected_checkout_time: string, grace_minutes: int|null}>
     */
    public function getWorkSchedulesForForm(): array
    {
        $existing = $this->workSchedules->keyBy('day_of_week');

        return collect(EmployeeWorkSchedule::DAY_LABELS)
            ->map(function (string $label, int $dayOfWeek) use ($existing) {
                $row = $existing->get($dayOfWeek);

                return [
                    'day_of_week' => $dayOfWeek,
                    'label' => $label,
                    'is_working' => $row ? (bool) $row->is_working : true,
                    'expected_checkin_time' => $row?->formattedExpectedCheckinTime() ?? '',
                    'expected_checkout_time' => $row?->formattedExpectedCheckoutTime() ?? '',
                    'grace_minutes' => $row?->grace_minutes,
                ];
            })
            ->values()
            ->all();
    }

    public function scheduleSummaryForDisplay(): ?string
    {
        if ($this->usesWeeklySchedule()) {
            $workingDays = $this->workSchedules->where('is_working', true);

            if ($workingDays->isEmpty()) {
                return 'بدون مواعيد';
            }

            $times = $workingDays
                ->map(fn (EmployeeWorkSchedule $row) => $row->formattedExpectedCheckinTime()
                    ?? $this->formattedExpectedCheckinTime())
                ->filter()
                ->unique()
                ->values();

            if ($times->count() === 1) {
                return $times->first();
            }

            return 'مواعيد مخصصة';
        }

        return $this->formattedExpectedCheckinTime();
    }

    public function formattedExpectedCheckinTime(): ?string
    {
        if (! $this->expected_checkin_time) {
            return null;
        }

        $parts = explode(':', (string) $this->expected_checkin_time);

        return sprintf('%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }

    public function formattedExpectedCheckoutTime(): ?string
    {
        if (! $this->expected_checkout_time) {
            return null;
        }

        $parts = explode(':', (string) $this->expected_checkout_time);

        return sprintf('%02d:%02d', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
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
     * عدد الحاضرين الآن داخل مجموعة الحضور لنفس الموظف.
     */
    public function getCurrentPresentCountInAttendanceGroup(): int
    {
        if ($this->attendance_group_id) {
            return Employee::where('attendance_group_id', $this->attendance_group_id)
                ->get()
                ->filter(fn (Employee $emp) => $emp->isCurrentlyPresent())
                ->count();
        }

        if (!$this->attendance_group_code) {
            return 0;
        }

        return Employee::where('attendance_group_code', $this->attendance_group_code)
            ->get()
            ->filter(fn (Employee $emp) => $emp->isCurrentlyPresent())
            ->count();
    }

    /**
     * رسالة منع الحضور بسبب حد أقصى لمجموعة حضور.
     */
    public function getAttendanceGroupCapacityBlockMessage(): ?string
    {
        if ($this->attendance_group_id && $this->attendanceGroup) {
            $presentCount = $this->getCurrentPresentCountInAttendanceGroup();
            if ($presentCount >= (int) $this->attendanceGroup->max_present) {
                return "لا يمكن تسجيل الحضور الآن. مجموعة ({$this->attendanceGroup->name}) وصلت للحد الأقصى ({$this->attendanceGroup->max_present}) حضور متزامن.";
            }

            return null;
        }

        if (!$this->attendance_group_code || !$this->attendance_group_max_present) {
            return null;
        }

        $presentCount = $this->getCurrentPresentCountInAttendanceGroup();
        if ($presentCount >= (int) $this->attendance_group_max_present) {
            return "لا يمكن تسجيل الحضور الآن. مجموعة ({$this->attendance_group_code}) وصلت للحد الأقصى ({$this->attendance_group_max_present}) حضور متزامن.";
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
        // الراتب الثابت لا يُحسب يومياً من الساعات
        if ($this->isFixedSalary()) {
            return 0;
        }

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
     * إجمالي ساعات العمل لفترة (كل يوم عمل 7 ص → 7 ص).
     */
    public function getHoursForPeriod($startDate, $endDate = null)
    {
        $endDate = $endDate ?? $startDate;
        $current = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $total = 0.0;

        while ($current->lte($end)) {
            $total += $this->getHoursForBusinessDayAnchor($current->toDateString());
            $current->addDay();
        }

        return $total;
    }

    /**
     * إجمالي المبلغ المستحق لفترة (كل يوم عمل 7 ص → 7 ص، بعد الخصومات).
     */
    public function getAmountForPeriod($startDate, $endDate = null)
    {
        $endDate = $endDate ?? $startDate;
        $current = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();
        $total = 0.0;

        while ($current->lte($end)) {
            $total += $this->getAmountForBusinessDayAnchor($current->toDateString());
            $current->addDay();
        }

        return $total;
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
     * إجمالي المبالغ التي سُلّمت فعلياً خلال يوم عمل (حسب delivered_at)، حتى لو كانت لأيام سابقة.
     */
    public function getAmountHandedOutDuringBusinessDay(string $anchorDate): float
    {
        return (float) $this->salaryDeliveries()
            ->deliveredDuringBusinessDay($anchorDate)
            ->sum('total_amount');
    }

    /**
     * إجمالي المبالغ التي سُلّمت فعلياً خلال فترة أيام عمل (حسب delivered_at).
     */
    public static function sumAmountHandedOutDuringBusinessDayRange(string $dateFrom, ?string $dateTo = null, ?callable $employeeConstraint = null): float
    {
        $query = SalaryDelivery::query()->where('status', 'delivered');

        if ($dateTo) {
            $query->deliveredDuringBusinessDayRange($dateFrom, $dateTo);
        } else {
            $query->deliveredDuringBusinessDay($dateFrom);
        }

        if ($employeeConstraint) {
            $query->whereHas('employee', $employeeConstraint);
        }

        return (float) $query->sum('total_amount');
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