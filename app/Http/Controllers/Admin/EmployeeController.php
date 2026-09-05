<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SalaryDelivery;
use App\Models\EmployeeDiscount;
use App\Models\EmployeeSalaryWithdrawal;
use App\Models\AttendanceGroup;
use App\Services\AttendanceLateDeductionService;
use App\Services\EmployeeSalaryWithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function __construct(
        private AttendanceLateDeductionService $lateDeductionService,
        private EmployeeSalaryWithdrawalService $withdrawalService
    ) {}
    /**
     * عرض صفحة إدارة الموظفين
     */
    public function index(Request $request)
    {
        $maxAnchor = Employee::businessDayAnchorFromNow();
        $selectedAnchor = $maxAnchor;

        if ($request->filled('date')) {
            $request->validate([
                'date' => 'date_format:Y-m-d',
            ]);
            $candidate = Carbon::parse($request->query('date'))->toDateString();
            if ($candidate <= $maxAnchor) {
                $selectedAnchor = $candidate;
            }
        }

        $isViewingTodayBusinessDay = $selectedAnchor === $maxAnchor;

        $employees = Employee::where('is_active', true)
            ->with(['attendanceDependencyEmployee:id,name', 'attendanceGroup:id,name,max_present', 'workSchedules'])
            ->get();

        // إضافة معلومات الحضور والرواتب ليوم العمل المحدد (7 ص → 7 ص)
        $employees->each(function ($employee) use ($selectedAnchor, $isViewingTodayBusinessDay) {
            $employee->current_attendance = $employee->getCurrentAttendance();
            $employee->is_present = $isViewingTodayBusinessDay && $employee->isCurrentlyPresent();
            $employee->today_hours = $employee->getHoursForBusinessDayAnchor($selectedAnchor);
            $employee->today_amount = $employee->getAmountForBusinessDayAnchor($selectedAnchor);
            $employee->today_attendance_records = $employee->getAttendanceRecordsForBusinessDayAnchor($selectedAnchor);

            $employee->today_discounts = $employee->getDiscountsForBusinessDayAnchor($selectedAnchor);
            $employee->today_discount_total = $employee->getDiscountTotalForBusinessDayAnchor($selectedAnchor);

            $employee->today_delivery_status = $employee->getSalaryDeliveryForDate($selectedAnchor);
            $employee->is_salary_delivered = $employee->today_delivery_status && $employee->today_delivery_status->isDelivered();
            $employee->delivery_status_text = $employee->today_delivery_status ? $employee->today_delivery_status->status_text : 'غير محدد';
            $employee->attendance_dependency_employee_name = optional($employee->attendanceDependencyEmployee)->name;
            $employee->attendance_group_name = optional($employee->attendanceGroup)->name;
            $employee->attendance_group_max_present = optional($employee->attendanceGroup)->max_present;
            $employee->expected_checkin_display = $employee->scheduleSummaryForDisplay();
            $employee->expected_checkout_display = $employee->formattedExpectedCheckoutTime();

            if ($employee->isFixedSalary()) {
                $monthKey = Carbon::parse($selectedAnchor)->format('Y-m');
                $summary = $employee->getFixedSalaryMonthSummary($monthKey);
                $canViewSalaryAmounts = $this->viewerCanSeeSalaryAmountsOnIndex();

                if ($canViewSalaryAmounts) {
                    $employee->fixed_salary_month = $summary;
                } else {
                    // لا نُظهر أرقام الراتب للكاشير/السوبر أدمن في القائمة؛ التفاصيل من صفحة المسحوبات
                    $employee->fixed_salary = null;
                    $employee->hourly_rate = null;
                    $employee->fixed_salary_month = [
                        'can_withdraw' => $summary['remaining'] > 0,
                    ];
                }
            } else {
                $employee->fixed_salary_month = null;
                if (! $this->viewerCanSeeSalaryAmountsOnIndex()) {
                    $employee->hourly_rate = null;
                    $employee->fixed_salary = null;
                }
            }
        });

        $totalTodayAmount = $employees->sum('today_amount');
        $totalTodayHours = $employees->sum('today_hours');

        $handedOutToday = SalaryDelivery::query()
            ->deliveredDuringBusinessDay($selectedAnchor)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->orderBy('salary_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        $employees->each(function ($employee) use ($handedOutToday, $selectedAnchor) {
            $items = collect($handedOutToday->get($employee->id, []));
            $employee->handed_out_today_amount = round((float) $items->sum('total_amount'), 2);
            $employee->handed_out_today_deliveries = $items->map(function (SalaryDelivery $delivery) use ($selectedAnchor) {
                $salaryDate = $delivery->salary_date?->toDateString();

                return [
                    'id' => $delivery->id,
                    'salary_date' => $salaryDate,
                    'salary_date_arabic' => $delivery->salary_date?->format('d/m/Y'),
                    'is_selected_day' => $salaryDate === $selectedAnchor,
                    'hours_worked' => round((float) $delivery->hours_worked, 2),
                    'total_amount' => round((float) $delivery->total_amount, 2),
                    'delivered_at' => optional($delivery->delivered_at)?->format('Y-m-d H:i:s'),
                ];
            })->values();
        });

        $totalDeliveredToday = round((float) $employees->sum('handed_out_today_amount'), 2);

        $currentPeriodText = Employee::periodTextForAnchorDate($selectedAnchor);

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'totalTodayAmount' => $totalTodayAmount,
            'totalTodayHours' => $totalTodayHours,
            'totalDeliveredToday' => round($totalDeliveredToday, 2),
            'currentPeriodText' => $currentPeriodText,
            'selectedDate' => $selectedAnchor,
            'maxSelectableDate' => $maxAnchor,
            'isViewingTodayBusinessDay' => $isViewingTodayBusinessDay,
        ]);
    }

    /**
     * عرض صفحة إضافة موظف جديد
     */
    public function create()
    {
        return Inertia::render('Admin/Employees/Create', [
            'employees' => Employee::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            'attendanceGroups' => AttendanceGroup::select('id', 'name', 'max_present')->orderBy('name')->get(),
            'canManageAttendanceDependency' => auth()->user()?->hasRole('super admin') ?? false,
            'canManageSchedule' => auth()->user()?->hasAnyRole(['admin', 'super admin']) ?? false,
        ]);
    }

    /**
     * حفظ موظف جديد
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'salary_type' => ['nullable', Rule::in([Employee::SALARY_TYPE_HOURLY, Employee::SALARY_TYPE_FIXED])],
            'hourly_rate' => 'nullable|numeric|min:0',
            'fixed_salary' => 'nullable|numeric|min:0',
            'allowed_vacation_days' => 'nullable|integer|min:0|max:31',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'attendance_dependency_employee_id' => 'nullable|exists:employees,id',
            'attendance_group_id' => 'nullable|exists:attendance_groups,id',
            'attendance_group_code' => 'nullable|string|max:100',
            'attendance_group_max_present' => 'nullable|integer|min:1|max:20',
            'expected_checkin_time' => 'nullable|date_format:H:i',
            'expected_checkout_time' => 'nullable|date_format:H:i',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'late_deductions_enabled' => 'boolean',
            'use_weekly_schedule' => 'boolean',
            'work_schedules' => 'nullable|array',
            'work_schedules.*.day_of_week' => 'required|integer|min:0|max:6',
            'work_schedules.*.is_working' => 'boolean',
            'work_schedules.*.expected_checkin_time' => 'nullable|date_format:H:i',
            'work_schedules.*.expected_checkout_time' => 'nullable|date_format:H:i',
            'work_schedules.*.grace_minutes' => 'nullable|integer|min:0|max:120',
        ]);

        $validated = $this->normalizeSalaryFields($validated, $request);

        if (! (auth()->user()?->hasAnyRole(['admin', 'super admin']) ?? false)) {
            unset($validated['expected_checkin_time'], $validated['expected_checkout_time'], $validated['grace_minutes'], $validated['late_deductions_enabled'], $validated['use_weekly_schedule'], $validated['work_schedules']);
            unset($validated['salary_type'], $validated['fixed_salary'], $validated['allowed_vacation_days']);
            $validated['salary_type'] = Employee::SALARY_TYPE_HOURLY;
            $validated['fixed_salary'] = null;
            $validated['allowed_vacation_days'] = 0;
        }

        if (! (auth()->user()?->hasRole('super admin') ?? false)) {
            $validated['attendance_dependency_employee_id'] = null;
            $validated['attendance_group_id'] = null;
            $validated['attendance_group_code'] = null;
            $validated['attendance_group_max_present'] = null;
        }

        if (empty($validated['attendance_group_code'])) {
            $validated['attendance_group_code'] = null;
            $validated['attendance_group_max_present'] = null;
        }

        $useWeeklySchedule = $request->boolean('use_weekly_schedule');
        $workSchedules = $request->input('work_schedules', []);
        unset($validated['use_weekly_schedule'], $validated['work_schedules']);

        $employee = Employee::create($validated);
        $this->syncWorkSchedules($employee, $useWeeklySchedule, $workSchedules);

        return redirect()->route('admin.employees.index')
            ->with('success', 'تم إضافة الموظف بنجاح');
    }

    /**
     * عرض صفحة تعديل موظف
     */
    public function edit(Employee $employee)
    {
        $employee->load('workSchedules');

        return Inertia::render('Admin/Employees/Edit', [
            'employee' => array_merge($employee->toArray(), [
                'expected_checkin_time' => $employee->formattedExpectedCheckinTime(),
                'expected_checkout_time' => $employee->formattedExpectedCheckoutTime(),
                'use_weekly_schedule' => $employee->usesWeeklySchedule(),
                'work_schedules' => $employee->getWorkSchedulesForForm(),
            ]),
            'employees' => Employee::where('is_active', true)
                ->where('id', '!=', $employee->id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'attendanceGroups' => AttendanceGroup::select('id', 'name', 'max_present')->orderBy('name')->get(),
            'canManageAttendanceDependency' => auth()->user()?->hasRole('super admin') ?? false,
            'canManageSchedule' => auth()->user()?->hasAnyRole(['admin', 'super admin']) ?? false,
        ]);
    }

    /**
     * تحديث بيانات موظف
     */
    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'salary_type' => ['nullable', Rule::in([Employee::SALARY_TYPE_HOURLY, Employee::SALARY_TYPE_FIXED])],
            'hourly_rate' => 'nullable|numeric|min:0',
            'fixed_salary' => 'nullable|numeric|min:0',
            'allowed_vacation_days' => 'nullable|integer|min:0|max:31',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'attendance_dependency_employee_id' => 'nullable|exists:employees,id|different:' . $employee->id,
            'attendance_group_id' => 'nullable|exists:attendance_groups,id',
            'attendance_group_code' => 'nullable|string|max:100',
            'attendance_group_max_present' => 'nullable|integer|min:1|max:20',
            'expected_checkin_time' => 'nullable|date_format:H:i',
            'expected_checkout_time' => 'nullable|date_format:H:i',
            'grace_minutes' => 'nullable|integer|min:0|max:120',
            'late_deductions_enabled' => 'boolean',
            'use_weekly_schedule' => 'boolean',
            'work_schedules' => 'nullable|array',
            'work_schedules.*.day_of_week' => 'required|integer|min:0|max:6',
            'work_schedules.*.is_working' => 'boolean',
            'work_schedules.*.expected_checkin_time' => 'nullable|date_format:H:i',
            'work_schedules.*.expected_checkout_time' => 'nullable|date_format:H:i',
            'work_schedules.*.grace_minutes' => 'nullable|integer|min:0|max:120',
        ]);

        $canManageSalaryType = auth()->user()?->hasAnyRole(['admin', 'super admin']) ?? false;
        if ($canManageSalaryType) {
            $validated = $this->normalizeSalaryFields($validated, $request);
        } else {
            unset($validated['salary_type'], $validated['fixed_salary'], $validated['hourly_rate'], $validated['allowed_vacation_days']);
        }

        if (! $canManageSalaryType) {
            unset($validated['expected_checkin_time'], $validated['expected_checkout_time'], $validated['grace_minutes'], $validated['late_deductions_enabled'], $validated['use_weekly_schedule'], $validated['work_schedules']);
        }

        if (! (auth()->user()?->hasRole('super admin') ?? false)) {
            unset($validated['attendance_dependency_employee_id']);
            unset($validated['attendance_group_id']);
            unset($validated['attendance_group_code']);
            unset($validated['attendance_group_max_present']);
        }

        if (array_key_exists('attendance_group_code', $validated) && empty($validated['attendance_group_code'])) {
            $validated['attendance_group_code'] = null;
            $validated['attendance_group_max_present'] = null;
        }

        $useWeeklySchedule = $request->boolean('use_weekly_schedule');
        $workSchedules = $request->input('work_schedules', []);
        unset($validated['use_weekly_schedule'], $validated['work_schedules']);

        $employee->update($validated);
        $this->syncWorkSchedules($employee, $useWeeklySchedule, $workSchedules);

        return redirect()->route('admin.employees.index')
            ->with('success', 'تم تحديث بيانات الموظف بنجاح');
    }

    /**
     * حذف موظف (سوبر أدمن فقط)
     */
    public function destroy(Request $request, Employee $employee)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        if ($employee->isCurrentlyPresent()) {
            return redirect()->route('admin.employees.index', array_filter([
                'date' => $request->query('date'),
            ]))->with('error', 'لا يمكن حذف موظف حاضر حالياً. سجّل انصرافه أولاً.');
        }

        $employee->delete();

        return redirect()->route('admin.employees.index', array_filter([
            'date' => $request->query('date'),
        ]))->with('success', 'تم حذف الموظف بنجاح');
    }

    /**
     * تسجيل حضور موظف
     */
    public function checkin(Employee $employee)
    {
        // التحقق من عدم وجود سجل حضور مفتوح لليوم الحالي
        if ($employee->isCurrentlyPresent()) {
            return response()->json([
                'success' => false,
                'message' => 'الموظف موجود بالفعل في العمل'
            ], 400);
        }

        $blockingEmployee = $employee->getAttendanceBlockingEmployee();
        if ($blockingEmployee) {
            return response()->json([
                'success' => false,
                'message' => "لا يمكن تسجيل حضور {$employee->name} الآن لأن {$blockingEmployee->name} ما زال في العمل.",
            ], 422);
        }

        $groupBlockMessage = $employee->getAttendanceGroupCapacityBlockMessage();
        if ($groupBlockMessage) {
            return response()->json([
                'success' => false,
                'message' => $groupBlockMessage,
            ], 422);
        }

        // إنشاء سجل حضور جديد
        $attendance = EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'checkin_time' => Carbon::now(),
        ]);

        $employee->load('workSchedules');
        $lateResult = $this->lateDeductionService->applyOnCheckin($employee, $attendance);

        // إعادة تحميل الموظف مع السجلات الجديدة
        $employee->refresh();

        // حساب الساعات والمبلغ المحدث
        $totalHours = $employee->getTodayHours();
        $totalAmount = $employee->getTodayAmount();
        $discountTotal = $employee->getTodayDiscountTotal();

        $response = [
            'success' => true,
            'message' => 'تم تسجيل الحضور بنجاح',
            'attendance' => $attendance->fresh(),
            'checkin_time' => $attendance->getFormattedCheckinTime(),
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
            'today_discount_total' => $discountTotal,
            'late_minutes' => $lateResult['late_minutes'],
        ];

        if (($lateResult['late_minutes'] ?? 0) > 0 && ! empty($lateResult['is_first_checkin'])) {
            $response['message'] = "تم تسجيل الحضور — تأخير {$lateResult['late_minutes']} دقيقة";
        }

        if (! empty($lateResult['discount_created']) && $lateResult['discount']) {
            $response['auto_discount'] = [
                'amount' => (float) $lateResult['discount']->amount,
                'reason' => $lateResult['discount']->reason,
            ];
            $response['message'] .= " — تم خصم {$lateResult['discount']->amount} جنيه تلقائياً";
        }

        return response()->json($response);
    }

    /**
     * تسجيل انصراف موظف
     */
    public function checkout(Employee $employee)
    {
        // البحث عن سجل الحضور المفتوح
        $attendance = $employee->getCurrentAttendance();
        
        // إضافة logging للتشخيص
        \Log::info('Employee checkout attempt', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'is_present' => $employee->isCurrentlyPresent(),
            'current_attendance' => $attendance,
            'all_open_attendances' => $employee->attendanceRecords()->whereNull('checkout_time')->get()
        ]);
        
        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد سجل حضور مفتوح لهذا الموظف'
            ], 400);
        }

        // تسجيل وقت الانصراف
        $attendance->checkout_time = Carbon::now();
        $attendance->calculateHoursAndAmount();
        $attendance->save();

        // إعادة تحميل الموظف مع السجلات الجديدة
        $employee->refresh();

        // حساب الساعات والمبلغ المحدث
        $totalHours = $employee->getTodayHours();
        $totalAmount = $employee->getTodayAmount();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الانصراف بنجاح',
            'attendance' => $attendance,
            'checkout_time' => $attendance->getFormattedCheckoutTime(),
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * عرض تقرير حضور موظف
     */
    public function report(Employee $employee, Request $request)
    {
        $dateFrom = $request->input('date_from', Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', Carbon::now()->endOfMonth()->toDateString());

        $attendances = $employee->attendanceRecords()
            ->whereNotNull('checkout_time')
            ->whereBetween('checkin_time', [$dateFrom, $dateTo])
            ->orderBy('checkin_time', 'desc')
            ->get();

        $totalHours = $employee->getHoursForPeriod($dateFrom, $dateTo);
        $totalAmount = $employee->getAmountForPeriod($dateFrom, $dateTo);

        return Inertia::render('Admin/Employees/Report', [
            'employee' => $employee,
            'attendances' => $attendances,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalHours' => $totalHours,
            'totalAmount' => $totalAmount,
        ]);
    }

    /**
     * عرض صفحة حاسبة الرواتب
     */
    public function salaryCalculator()
    {
        $employees = Employee::where('is_active', true)->get();

        return Inertia::render('Admin/Employees/SalaryCalculator', [
            'employees' => $employees,
        ]);
    }

    /**
     * حساب راتب موظف لفترة محددة (من 7 صباحاً إلى 7 صباحاً للوم التالي)
     */
    public function calculateSalary(Employee $employee, Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // تحويل التواريخ إلى فترات زمنية (7 صباحاً إلى 7 صباحاً للوم التالي)
        $startDateTime = Carbon::parse($dateFrom)->setTime(7, 0, 0);
        $endDateTime = Carbon::parse($dateTo)->addDay()->setTime(7, 0, 0);

        // البحث عن سجلات الحضور في الفترة المحددة (جميع السجلات)
        $attendances = $employee->attendanceRecords()
            ->whereBetween('checkin_time', [$startDateTime, $endDateTime])
            ->orderBy('checkin_time', 'asc')
            ->get();

        $totalHours = 0;
        $totalAmount = 0;
        $totalBaseAmount = 0;
        $totalDiscounts = 0;
        $dailyDetails = [];

        // تجميع البيانات حسب اليوم
        $currentDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);

        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->setTime(7, 0, 0);
            $dayEnd = $currentDate->copy()->addDay()->setTime(7, 0, 0);

            // البحث عن سجلات الحضور لهذا اليوم
            $dayAttendances = $attendances->filter(function ($attendance) use ($dayStart, $dayEnd) {
                $checkinTime = Carbon::parse($attendance->checkin_time);
                return $checkinTime >= $dayStart && $checkinTime < $dayEnd;
            })->sortBy('checkin_time'); // ترتيب السجلات حسب وقت الحضور

            $dayHours = 0;
            $dayAmount = 0;
            $dayRecords = [];

            foreach ($dayAttendances as $attendance) {
                $checkinTime = Carbon::parse($attendance->checkin_time);
                
                // تحديد وقت الانصراف
                if ($attendance->checkout_time) {
                    $checkoutTime = Carbon::parse($attendance->checkout_time);
                } else {
                    // إذا لم يكن هناك وقت انصراف، نستخدم الوقت الحالي أو نهاية اليوم
                    $checkoutTime = Carbon::now();
                    if ($checkoutTime > $dayEnd) {
                        $checkoutTime = $dayEnd;
                    }
                }

                // التأكد من أن وقت الانصراف لا يتجاوز نهاية اليوم
                if ($checkoutTime > $dayEnd) {
                    $checkoutTime = $dayEnd;
                }

                // التأكد من أن وقت الحضور لا يسبق بداية اليوم
                if ($checkinTime < $dayStart) {
                    $checkinTime = $dayStart;
                }

                // التأكد من أن وقت الحضور لا يتجاوز وقت الانصراف
                if ($checkinTime >= $checkoutTime) {
                    continue; // تخطي هذا السجل إذا كان وقت الحضور بعد أو يساوي وقت الانصراف
                }

                $hours = $checkinTime->diffInHours($checkoutTime, true);
                $amount = $hours * $employee->hourly_rate;

                // تجاهل السجلات التي لا تحتوي على ساعات عمل
                if ($hours <= 0) {
                    continue;
                }

                $dayHours += $hours;
                $dayAmount += $amount;

                $dayRecords[] = [
                    'checkin_time' => $checkinTime->format('H:i'),
                    'checkout_time' => $checkoutTime->format('H:i'),
                    'hours' => round($hours, 2),
                    'amount' => round($amount, 2),
                    'is_completed' => $attendance->checkout_time !== null,
                ];
            }

            // حساب الخصومات لهذا اليوم
            $dateString = $currentDate->format('Y-m-d');
            $dayDiscounts = $employee->discounts()
                ->where('discount_date', $dateString)
                ->get();
            $dayDiscountTotal = $dayDiscounts->sum('amount');
            
            // خصم الخصومات من المبلغ اليومي
            $dayAmountAfterDiscount = max(0, $dayAmount - $dayDiscountTotal);

            $totalHours += $dayHours;
            $totalAmount += $dayAmountAfterDiscount; // استخدام المبلغ بعد الخصم في الإجمالي
            $totalBaseAmount += $dayAmount; // المبلغ الأصلي قبل الخصم
            $totalDiscounts += $dayDiscountTotal; // إجمالي الخصومات

            // البحث عن حالة تسليم الراتب لهذا اليوم
            $salaryDelivery = $employee->getSalaryDeliveryForDate($dateString);
            
            $dailyDetails[] = [
                'date' => $dateString,
                'date_arabic' => $currentDate->format('d/m/Y'),
                'day_name' => $currentDate->locale('ar')->dayName,
                'hours' => round($dayHours, 2),
                'amount' => round($dayAmountAfterDiscount, 2), // المبلغ بعد الخصم
                'base_amount' => round($dayAmount, 2), // المبلغ الأصلي قبل الخصم
                'discounts' => $dayDiscounts->map(function ($discount) {
                    return [
                        'id' => $discount->id,
                        'amount' => $discount->amount,
                        'reason' => $discount->reason,
                        'source' => $discount->source ?? 'manual',
                        'is_automatic' => ($discount->source ?? 'manual') === 'late_rule',
                        'created_at' => $discount->created_at->format('Y-m-d H:i:s'),
                    ];
                })->toArray(),
                'discount_total' => round($dayDiscountTotal, 2),
                'records' => $dayRecords,
                'has_records' => count($dayRecords) > 0,
                'delivery_status' => $salaryDelivery ? [
                    'is_delivered' => $salaryDelivery->isDelivered(),
                    'status' => $salaryDelivery->status,
                    'status_text' => $salaryDelivery->status_text,
                    'delivered_at' => $salaryDelivery->delivered_at_formatted,
                    'delivered_amount' => $salaryDelivery->total_amount
                ] : null,
            ];

            $currentDate->addDay();
        }

        $periodDiscounts = $employee->discounts()
            ->whereBetween('discount_date', [$dateFrom, $dateTo])
            ->orderBy('discount_date')
            ->orderBy('created_at')
            ->get();

        $automaticDiscounts = $periodDiscounts->where('source', 'late_rule');
        $manualDiscounts = $periodDiscounts->where('source', '!=', 'late_rule');

        $discountSummary = [
            'total' => round((float) $periodDiscounts->sum('amount'), 2),
            'count' => $periodDiscounts->count(),
            'automatic_total' => round((float) $automaticDiscounts->sum('amount'), 2),
            'automatic_count' => $automaticDiscounts->count(),
            'manual_total' => round((float) $manualDiscounts->sum('amount'), 2),
            'manual_count' => $manualDiscounts->count(),
            'days_with_discounts' => $periodDiscounts->pluck('discount_date')->unique()->count(),
            'items' => $periodDiscounts->map(function ($discount) {
                $date = Carbon::parse($discount->discount_date);

                return [
                    'id' => $discount->id,
                    'date' => $date->format('Y-m-d'),
                    'date_arabic' => $date->format('d/m/Y'),
                    'day_name' => $date->locale('ar')->dayName,
                    'amount' => round((float) $discount->amount, 2),
                    'reason' => $discount->reason,
                    'source' => $discount->source ?? 'manual',
                    'is_automatic' => ($discount->source ?? 'manual') === 'late_rule',
                    'created_at' => $discount->created_at->format('Y-m-d H:i'),
                ];
            })->values()->all(),
        ];

        $todayAnchor = Employee::businessDayAnchorFromNow();
        $todayDueAmount = $employee->getAmountForBusinessDayAnchor($todayAnchor);
        $handedOutToday = $employee->getAmountHandedOutDuringBusinessDay($todayAnchor);
        $todayDelivery = $employee->getSalaryDeliveryForDate($todayAnchor);

        return response()->json([
            'success' => true,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'hourly_rate' => $employee->hourly_rate,
                'position' => $employee->position,
            ],
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_from_arabic' => Carbon::parse($dateFrom)->format('d/m/Y'),
                'date_to_arabic' => Carbon::parse($dateTo)->format('d/m/Y'),
            ],
            'summary' => [
                'total_hours' => round($totalHours, 2),
                'total_amount' => round($totalAmount, 2), // المبلغ بعد الخصم
                'total_base_amount' => round($totalBaseAmount, 2), // المبلغ الأصلي قبل الخصم
                'total_discounts' => round($totalDiscounts, 2), // إجمالي الخصومات
                'days_count' => count($dailyDetails),
                'days_with_records' => count(array_filter($dailyDetails, fn($day) => $day['has_records'])),
            ],
            'today_cash' => [
                'anchor_date' => $todayAnchor,
                'period_text' => Employee::periodTextForAnchorDate($todayAnchor),
                'due_today' => round($todayDueAmount, 2),
                'handed_out_today' => round($handedOutToday, 2),
                'today_salary_delivered' => $todayDelivery && $todayDelivery->isDelivered(),
            ],
            'discount_summary' => $discountSummary,
            'daily_details' => $dailyDetails,
            'debug_info' => [
                'total_attendances_found' => $attendances->count(),
                'period_start' => $startDateTime->format('Y-m-d H:i:s'),
                'period_end' => $endDateTime->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * تسليم راتب موظف لليوم الحالي
     */
    public function deliverSalary(Employee $employee)
    {
        try {
            // التأكد من وجود ساعات عمل لليوم
            $todayHours = $employee->getTodayHours();
            $todayAmount = $employee->getTodayAmount();

            if ($todayHours <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ساعات عمل مسجلة لهذا الموظف اليوم'
                ], 400);
            }

            // إنشاء أو تحديث سجل تسليم الراتب
            $delivery = $employee->createOrUpdateTodayDelivery();

            // التحقق من حالة التسليم
            if ($delivery->isDelivered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم تسليم راتب هذا الموظف مسبقاً'
                ], 400);
            }

            // تحديد الراتب كمسلم
            $delivery->markAsDelivered(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم تسليم الراتب بنجاح',
                'delivery' => [
                    'id' => $delivery->id,
                    'status' => $delivery->status,
                    'status_text' => $delivery->status_text,
                    'delivered_at' => $delivery->delivered_at_formatted,
                    'total_amount' => $delivery->total_amount,
                    'hours_worked' => $delivery->hours_worked
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسليم الراتب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إلغاء تسليم راتب موظف (إعادة الحالة إلى في الانتظار)
     */
    public function undoSalaryDelivery(Employee $employee)
    {
        try {
            $delivery = $employee->getTodayDeliveryStatus();

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد سجل راتب لهذا الموظف اليوم'
                ], 400);
            }

            if (!$delivery->isDelivered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'راتب هذا الموظف لم يتم تسليمه بعد'
                ], 400);
            }

            // إعادة الحالة إلى في الانتظار
            $delivery->update([
                'status' => 'pending',
                'delivered_at' => null,
                'delivered_by' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسليم الراتب بنجاح',
                'delivery' => [
                    'id' => $delivery->id,
                    'status' => $delivery->status,
                    'status_text' => $delivery->status_text,
                    'total_amount' => $delivery->total_amount,
                    'hours_worked' => $delivery->hours_worked
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء تسليم الراتب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تسليم راتب موظف ليوم محدد
     */
    public function deliverSalaryForDate(Employee $employee, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $date = Carbon::parse($request->input('date'))->toDateString();
            $hours = $employee->getHoursForBusinessDayAnchor($date);
            $amount = $employee->getAmountForBusinessDayAnchor($date);

            if ($hours <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد ساعات عمل مسجلة لهذا الموظف في هذا التاريخ'
                ], 400);
            }

            $salaryDelivery = $employee->getSalaryDeliveryForDate($date);

            if ($salaryDelivery && $salaryDelivery->isDelivered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم تسليم راتب هذا الموظف لهذا التاريخ مسبقاً'
                ], 400);
            }

            if (! $salaryDelivery) {
                $salaryDelivery = $employee->createSalaryDelivery($date, $hours, $amount);
            } else {
                $salaryDelivery->update([
                    'hours_worked' => $hours,
                    'hourly_rate' => $employee->hourly_rate,
                    'total_amount' => $amount,
                ]);
            }

            $salaryDelivery->markAsDelivered(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'تم تسليم راتب اليوم بنجاح',
                'delivery' => [
                    'id' => $salaryDelivery->id,
                    'date' => $salaryDelivery->salary_date,
                    'status' => $salaryDelivery->status,
                    'status_text' => $salaryDelivery->status_text,
                    'delivered_at' => $salaryDelivery->delivered_at_formatted,
                    'total_amount' => $salaryDelivery->total_amount,
                    'hours_worked' => $salaryDelivery->hours_worked
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسليم الراتب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إلغاء تسليم راتب موظف لتاريخ محدد
     */
    public function undoSalaryDeliveryForDate(Employee $employee, Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $date = $request->input('date');
            $delivery = $employee->getSalaryDeliveryForDate($date);

            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد سجل راتب لهذا الموظف في هذا التاريخ'
                ], 400);
            }

            if (!$delivery->isDelivered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'راتب هذا الموظف لم يتم تسليمه في هذا التاريخ'
                ], 400);
            }

            // إعادة الحالة إلى في الانتظار
            $delivery->update([
                'status' => 'pending',
                'delivered_at' => null,
                'delivered_by' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء تسليم الراتب بنجاح',
                'delivery' => [
                    'id' => $delivery->id,
                    'date' => $delivery->salary_date,
                    'status' => $delivery->status,
                    'status_text' => $delivery->status_text,
                    'total_amount' => $delivery->total_amount,
                    'hours_worked' => $delivery->hours_worked
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء تسليم الراتب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تسليم رواتب موظف لفترة محددة (كل الأيام غير المسلمة)
     */
    public function deliverSalaryForPeriod(Employee $employee, Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        try {
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $deliveredDays = [];
            $skippedDays = [];
            $currentDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);

            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('Y-m-d');
                $dayHours = $employee->getHoursForBusinessDayAnchor($dateString);
                $dayAmount = $employee->getAmountForBusinessDayAnchor($dateString);

                if ($dayHours > 0) {
                    $salaryDelivery = $employee->getSalaryDeliveryForDate($dateString);

                    if (! $salaryDelivery) {
                        $salaryDelivery = $employee->createSalaryDelivery($dateString, $dayHours, $dayAmount);
                    }

                    if (! $salaryDelivery->isDelivered()) {
                        $salaryDelivery->update([
                            'hours_worked' => $dayHours,
                            'hourly_rate' => $employee->hourly_rate,
                            'total_amount' => $dayAmount,
                        ]);

                        $salaryDelivery->markAsDelivered(auth()->id());

                        $deliveredDays[] = [
                            'date' => $dateString,
                            'date_arabic' => $currentDate->format('d/m/Y'),
                            'hours' => round($dayHours, 2),
                            'amount' => round($dayAmount, 2),
                        ];
                    } else {
                        $skippedDays[] = [
                            'date' => $dateString,
                            'date_arabic' => $currentDate->format('d/m/Y'),
                            'reason' => 'تم تسليمه مسبقاً',
                        ];
                    }
                }

                $currentDate->addDay();
            }

            $totalDelivered = count($deliveredDays);
            $totalAmount = array_sum(array_column($deliveredDays, 'amount'));

            return response()->json([
                'success' => true,
                'message' => "تم تسليم رواتب {$totalDelivered} أيام بإجمالي " . number_format($totalAmount, 2),
                'delivered_days' => $deliveredDays,
                'skipped_days' => $skippedDays,
                'summary' => [
                    'total_days_delivered' => $totalDelivered,
                    'total_days_skipped' => count($skippedDays),
                    'total_amount_delivered' => $totalAmount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسليم الرواتب: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * إضافة خصم لموظف لليوم الحالي
     */
    public function addDiscount(Employee $employee, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:1000',
            'discount_date' => 'nullable|date_format:Y-m-d',
        ]);

        try {
            $maxAnchor = Employee::businessDayAnchorFromNow();
            $targetDate = isset($validated['discount_date'])
                ? Carbon::parse($validated['discount_date'])->toDateString()
                : $maxAnchor;

            if ($targetDate > $maxAnchor) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن إضافة خصم لتاريخ بعد يوم العمل الحالي.',
                ], 422);
            }

            if ($employee->isFixedSalary()) {
                $monthKey = Carbon::parse($targetDate)->format('Y-m');
                $summary = $employee->getFixedSalaryMonthSummary($monthKey);
                if ((float) $request->amount > $summary['remaining'] + 0.0001) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'مبلغ الخصم أكبر من المتبقي من الراتب الثابت هذا الشهر (%.2f).',
                            $summary['remaining']
                        ),
                    ], 422);
                }
            }

            // إنشاء سجل الخصم
            $discount = EmployeeDiscount::create([
                'employee_id' => $employee->id,
                'discount_date' => $targetDate,
                'amount' => $request->amount,
                'reason' => $request->reason,
                'created_by' => auth()->id(),
            ]);

            // إعادة تحميل الموظف مع السجلات الجديدة
            $employee->refresh();

            // حساب المبلغ المحدث بعد الخصم لنفس يوم العمل
            $todayAmount = $employee->getAmountForBusinessDayAnchor($targetDate);
            $discountTotal = $employee->getDiscountTotalForBusinessDayAnchor($targetDate);
            $fixedSalaryMonth = $employee->isFixedSalary()
                ? $employee->getFixedSalaryMonthSummary(Carbon::parse($targetDate)->format('Y-m'))
                : null;

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الخصم بنجاح',
                'discount' => [
                    'id' => $discount->id,
                    'amount' => $discount->amount,
                    'reason' => $discount->reason,
                    'discount_date' => $discount->discount_date,
                    'created_at' => $discount->created_at->format('Y-m-d H:i:s'),
                ],
                'employee' => [
                    'today_amount' => $todayAmount,
                    'today_discount_total' => $discountTotal,
                    'fixed_salary_month' => $fixedSalaryMonth,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الخصم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * سحب جزء من الراتب الثابت (متاح للكاشير والمدير والسوبر أدمن)
     */
    public function withdrawSalary(Employee $employee, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $withdrawal = $this->withdrawalService->withdraw(
                $employee,
                (float) $validated['amount'],
                $validated['notes'] ?? null
            );

            $employee->refresh();
            $summary = $employee->getFixedSalaryMonthSummary($withdrawal->year_month);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل المسحوب بنجاح وإضافته للمصروفات',
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => (float) $withdrawal->amount,
                    'withdrawal_date' => $withdrawal->withdrawal_date->toDateString(),
                    'notes' => $withdrawal->notes,
                    'expense_id' => $withdrawal->expense_id,
                ],
                'fixed_salary_month' => $summary,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'تعذر تسجيل المسحوب',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تسجيل المسحوب: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * تقرير مسحوبات الرواتب الثابتة (مدير / سوبر أدمن)
     */
    public function salaryWithdrawals(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'super admin']), 403);

        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'employee_id' => 'nullable|integer|exists:employees,id',
        ]);

        $yearMonth = $request->input('month') ?: Carbon::now()->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $yearMonth));
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth();

        $fixedEmployees = Employee::query()
            ->where('salary_type', Employee::SALARY_TYPE_FIXED)
            ->where('is_active', true)
            ->when($request->filled('employee_id'), fn ($q) => $q->where('id', $request->integer('employee_id')))
            ->with('workSchedules')
            ->orderBy('name')
            ->get();

        $rows = $fixedEmployees->map(function (Employee $employee) use ($yearMonth, $start, $end) {
            $summary = $employee->getFixedSalaryMonthSummary($yearMonth);
            $withdrawals = $employee->salaryWithdrawals()
                ->where('year_month', $yearMonth)
                ->with(['createdBy:id,name', 'expense:id,description,expense_date'])
                ->orderByDesc('withdrawal_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (EmployeeSalaryWithdrawal $w) => [
                    'id' => $w->id,
                    'amount' => (float) $w->amount,
                    'withdrawal_date' => $w->withdrawal_date->toDateString(),
                    'notes' => $w->notes,
                    'created_by_name' => optional($w->createdBy)->name,
                    'created_at' => optional($w->created_at)?->toDateTimeString(),
                    'expense_id' => $w->expense_id,
                ]);

            $discounts = $employee->discounts()
                ->whereDate('discount_date', '>=', $start->toDateString())
                ->whereDate('discount_date', '<=', $end->toDateString())
                ->orderByDesc('discount_date')
                ->get()
                ->map(fn (EmployeeDiscount $d) => [
                    'id' => $d->id,
                    'amount' => (float) $d->amount,
                    'discount_date' => Carbon::parse($d->discount_date)->toDateString(),
                    'reason' => $d->reason,
                    'source' => $d->source,
                ]);

            $attendanceSummary = $employee->getMonthlyAttendanceSummary($yearMonth);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'position' => $employee->position,
                'fixed_salary' => $summary['fixed_salary'],
                'allowed_vacation_days' => $attendanceSummary['allowed_vacation_days'],
                'withdrawals_total' => $summary['withdrawals_total'],
                'discounts_total' => $summary['discounts_total'],
                'remaining' => $summary['remaining'],
                'withdrawals_count' => $summary['withdrawals_count'],
                'withdrawals' => $withdrawals,
                'discounts' => $discounts,
                'absence_days_count' => $attendanceSummary['absence_days_count'],
                'excess_absence_days' => $attendanceSummary['excess_absence_days'],
                'daily_salary_rate' => $attendanceSummary['daily_salary_rate'],
                'absence_deduction_amount' => $attendanceSummary['absence_deduction_amount'],
                'absence_dates' => $attendanceSummary['absence_dates'],
                'daily_log' => $attendanceSummary['daily_log'],
            ];
        })->values();

        return Inertia::render('Admin/Employees/SalaryWithdrawals', [
            'month' => $yearMonth,
            'employees' => $rows,
            'totals' => [
                'fixed_salary' => round($rows->sum('fixed_salary'), 2),
                'withdrawals_total' => round($rows->sum('withdrawals_total'), 2),
                'discounts_total' => round($rows->sum('discounts_total'), 2),
                'remaining' => round($rows->sum('remaining'), 2),
            ],
            'employeeFilterOptions' => Employee::where('salary_type', Employee::SALARY_TYPE_FIXED)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedEmployeeId' => $request->integer('employee_id') ?: null,
        ]);
    }

    /**
     * إلغاء مسحوب (مدير / سوبر أدمن) مع حذف المصروف المرتبط
     */
    public function cancelSalaryWithdrawal(Employee $employee, EmployeeSalaryWithdrawal $withdrawal)
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'super admin']), 403);

        if ((int) $withdrawal->employee_id !== (int) $employee->id) {
            return response()->json([
                'success' => false,
                'message' => 'المسحوب غير مرتبط بهذا الموظف.',
            ], 404);
        }

        try {
            $this->withdrawalService->cancel($withdrawal);
            $employee->refresh();
            $summary = $employee->getFixedSalaryMonthSummary(Carbon::now()->format('Y-m'));

            return response()->json([
                'success' => true,
                'message' => 'تم إلغاء المسحوب وحذف المصروف المرتبط به',
                'fixed_salary_month' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إلغاء المسحوب: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * إزالة خصم من موظف (سوبر أدمن فقط)
     */
    public function removeDiscount(Employee $employee, EmployeeDiscount $discount)
    {
        abort_unless(auth()->user()?->hasRole('super admin'), 403);

        if ((int) $discount->employee_id !== (int) $employee->id) {
            return response()->json([
                'success' => false,
                'message' => 'الخصم غير مرتبط بهذا الموظف.',
            ], 404);
        }

        try {
            $discountDate = Carbon::parse($discount->discount_date)->toDateString();
            $discount->delete();

            $employee->refresh();
            $todayAmount = $employee->getAmountForBusinessDayAnchor($discountDate);
            $discountTotal = $employee->getDiscountTotalForBusinessDayAnchor($discountDate);

            return response()->json([
                'success' => true,
                'message' => 'تم إزالة الخصم بنجاح',
                'employee' => [
                    'today_amount' => $todayAmount,
                    'today_discount_total' => $discountTotal,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إزالة الخصم: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * أرقام الراتب لا تُعرض في قائمة الموظفين لأي دور.
     * المدير والسوبر أدمن يطّلعان عليها من صفحة مسحوبات الرواتب فقط.
     */
    private function viewerCanSeeSalaryAmountsOnIndex(): bool
    {
        return false;
    }

    /**
     * توحيد حقول نوع الراتب قبل الحفظ.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeSalaryFields(array $validated, Request $request): array
    {
        $salaryType = $validated['salary_type'] ?? Employee::SALARY_TYPE_HOURLY;
        $validated['salary_type'] = $salaryType;

        if ($salaryType === Employee::SALARY_TYPE_FIXED) {
            if (! $request->filled('fixed_salary') || (float) $request->input('fixed_salary') <= 0) {
                throw ValidationException::withMessages([
                    'fixed_salary' => 'الراتب الشهري الثابت مطلوب ويجب أن يكون أكبر من صفر.',
                ]);
            }
            $validated['fixed_salary'] = (float) $request->input('fixed_salary');
            $validated['hourly_rate'] = $request->filled('hourly_rate')
                ? (float) $request->input('hourly_rate')
                : 0;
            $validated['allowed_vacation_days'] = max(0, min(31, (int) ($request->input('allowed_vacation_days') ?? 0)));
        } else {
            if (! $request->filled('hourly_rate') || (float) $request->input('hourly_rate') < 0) {
                throw ValidationException::withMessages([
                    'hourly_rate' => 'سعر الساعة مطلوب للموظفين بنظام الساعات.',
                ]);
            }
            $validated['hourly_rate'] = (float) $request->input('hourly_rate');
            $validated['fixed_salary'] = null;
            $validated['allowed_vacation_days'] = 0;
        }

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $workSchedules
     */
    private function syncWorkSchedules(Employee $employee, bool $useWeeklySchedule, array $workSchedules): void
    {
        if (! $useWeeklySchedule) {
            $employee->workSchedules()->delete();

            return;
        }

        $submittedDays = collect($workSchedules)->pluck('day_of_week')->all();

        foreach ($workSchedules as $row) {
            $employee->workSchedules()->updateOrCreate(
                ['day_of_week' => (int) $row['day_of_week']],
                [
                    'is_working' => (bool) ($row['is_working'] ?? false),
                    'expected_checkin_time' => ! empty($row['expected_checkin_time']) ? $row['expected_checkin_time'] : null,
                    'expected_checkout_time' => ! empty($row['expected_checkout_time']) ? $row['expected_checkout_time'] : null,
                    'grace_minutes' => isset($row['grace_minutes']) && $row['grace_minutes'] !== '' && $row['grace_minutes'] !== null
                        ? (int) $row['grace_minutes']
                        : null,
                ]
            );
        }

        $employee->workSchedules()
            ->whereNotIn('day_of_week', $submittedDays)
            ->delete();
    }
} 