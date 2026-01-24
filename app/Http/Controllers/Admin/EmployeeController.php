<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SalaryDelivery;
use App\Models\EmployeeDiscount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /**
     * عرض صفحة إدارة الموظفين
     */
    public function index()
    {
        $employees = Employee::where('is_active', true)->get();

            // إضافة معلومات الحضور والرواتب الحالية لكل موظف
    $employees->each(function ($employee) {
        $employee->current_attendance = $employee->getCurrentAttendance();
        $employee->is_present = $employee->isCurrentlyPresent();
        $employee->today_hours = $employee->getTodayHours();
        $employee->today_amount = $employee->getTodayAmount();
        $employee->today_attendance_records = $employee->getTodayAttendanceRecords();
        
        // معلومات الخصومات اليومية
        $employee->today_discounts = $employee->getTodayDiscounts();
        $employee->today_discount_total = $employee->getTodayDiscountTotal();
        
        // معلومات تسليم الراتب
        $employee->today_delivery_status = $employee->getTodayDeliveryStatus();
        $employee->is_salary_delivered = $employee->today_delivery_status && $employee->today_delivery_status->isDelivered();
        $employee->delivery_status_text = $employee->today_delivery_status ? $employee->today_delivery_status->status_text : 'غير محدد';
    });

        // حساب إجمالي الرواتب لليوم الحالي
        $totalTodayAmount = $employees->sum('today_amount');
        $totalTodayHours = $employees->sum('today_hours');
        $currentPeriodText = $employees->first() ? $employees->first()->getCurrentPeriodText() : '';

        return Inertia::render('Admin/Employees/Index', [
            'employees' => $employees,
            'totalTodayAmount' => $totalTodayAmount,
            'totalTodayHours' => $totalTodayHours,
            'currentPeriodText' => $currentPeriodText,
        ]);
    }

    /**
     * عرض صفحة إضافة موظف جديد
     */
    public function create()
    {
        return Inertia::render('Admin/Employees/Create');
    }

    /**
     * حفظ موظف جديد
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'hourly_rate' => 'required|numeric|min:0',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Employee::create($request->all());

        return redirect()->route('admin.employees.index')
            ->with('success', 'تم إضافة الموظف بنجاح');
    }

    /**
     * عرض صفحة تعديل موظف
     */
    public function edit(Employee $employee)
    {
        return Inertia::render('Admin/Employees/Edit', [
            'employee' => $employee,
        ]);
    }

    /**
     * تحديث بيانات موظف
     */
    public function update(Request $request, Employee $employee)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'hourly_rate' => 'required|numeric|min:0',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $employee->update($request->all());

        return redirect()->route('admin.employees.index')
            ->with('success', 'تم تحديث بيانات الموظف بنجاح');
    }

    /**
     * حذف موظف
     */
    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route('admin.employees.index')
            ->with('success', 'تم حذف الموظف بنجاح');
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

        // إنشاء سجل حضور جديد
        $attendance = EmployeeAttendance::create([
            'employee_id' => $employee->id,
            'checkin_time' => Carbon::now(),
        ]);

        // إعادة تحميل الموظف مع السجلات الجديدة
        $employee->refresh();

        // حساب الساعات والمبلغ المحدث
        $totalHours = $employee->getTodayHours();
        $totalAmount = $employee->getTodayAmount();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الحضور بنجاح',
            'attendance' => $attendance,
            'checkin_time' => $attendance->getFormattedCheckinTime(),
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
        ]);
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

            $totalHours += $dayHours;
            $totalAmount += $dayAmount;

            // البحث عن حالة تسليم الراتب لهذا اليوم
            $salaryDelivery = $employee->getSalaryDeliveryForDate($currentDate->format('Y-m-d'));
            
            $dailyDetails[] = [
                'date' => $currentDate->format('Y-m-d'),
                'date_arabic' => $currentDate->format('d/m/Y'),
                'day_name' => $currentDate->locale('ar')->dayName,
                'hours' => round($dayHours, 2),
                'amount' => round($dayAmount, 2),
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
                'total_amount' => round($totalAmount, 2),
                'days_count' => count($dailyDetails),
                'days_with_records' => count(array_filter($dailyDetails, fn($day) => $day['has_records'])),
            ],
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
            $date = $request->input('date');
            
            // البحث عن سجل التسليم لهذا التاريخ أو إنشاؤه
            $salaryDelivery = $employee->getSalaryDeliveryForDate($date);
            
            if (!$salaryDelivery) {
                // حساب الساعات والمبلغ لهذا اليوم
                $hours = $employee->getHoursForPeriod($date, $date);
                $amount = $employee->getAmountForPeriod($date, $date);
                
                if ($hours <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا توجد ساعات عمل مسجلة لهذا الموظف في هذا التاريخ'
                    ], 400);
                }
                
                // إنشاء سجل تسليم جديد
                $salaryDelivery = $employee->createSalaryDelivery($date, $hours, $amount);
            }

            // التحقق من حالة التسليم
            if ($salaryDelivery->isDelivered()) {
                return response()->json([
                    'success' => false,
                    'message' => 'تم تسليم راتب هذا الموظف لهذا التاريخ مسبقاً'
                ], 400);
            }

            // تحديد الراتب كمسلم
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
            
            // تحويل التواريخ إلى فترات زمنية (7 صباحاً إلى 7 صباحاً للوم التالي)
            $startDateTime = Carbon::parse($dateFrom)->setTime(7, 0, 0);
            $endDateTime = Carbon::parse($dateTo)->addDay()->setTime(7, 0, 0);

            // البحث عن سجلات الحضور في الفترة المحددة
            $attendances = $employee->attendanceRecords()
                ->whereBetween('checkin_time', [$startDateTime, $endDateTime])
                ->orderBy('checkin_time', 'asc')
                ->get();

            $deliveredDays = [];
            $skippedDays = [];
            $currentDate = Carbon::parse($dateFrom);
            $endDate = Carbon::parse($dateTo);

            while ($currentDate <= $endDate) {
                $dayStart = $currentDate->copy()->setTime(7, 0, 0);
                $dayEnd = $currentDate->copy()->addDay()->setTime(7, 0, 0);
                $dateString = $currentDate->format('Y-m-d');

                // البحث عن سجلات الحضور لهذا اليوم
                $dayAttendances = $attendances->filter(function ($attendance) use ($dayStart, $dayEnd) {
                    $checkinTime = Carbon::parse($attendance->checkin_time);
                    return $checkinTime >= $dayStart && $checkinTime < $dayEnd;
                });

                // حساب الساعات والمبلغ لهذا اليوم
                $dayHours = 0;
                foreach ($dayAttendances as $attendance) {
                    if ($attendance->checkout_time) {
                        $checkinTime = Carbon::parse($attendance->checkin_time);
                        $checkoutTime = Carbon::parse($attendance->checkout_time);
                        
                        // التأكد من الحدود الزمنية
                        if ($checkinTime < $dayStart) $checkinTime = $dayStart;
                        if ($checkoutTime > $dayEnd) $checkoutTime = $dayEnd;
                        if ($checkinTime < $checkoutTime) {
                            $dayHours += $checkinTime->diffInHours($checkoutTime, true);
                        }
                    }
                }

                $dayAmount = $dayHours * $employee->hourly_rate;

                // إذا كان هناك ساعات عمل في هذا اليوم
                if ($dayHours > 0) {
                    // البحث عن سجل التسليم الموجود
                    $salaryDelivery = $employee->getSalaryDeliveryForDate($dateString);
                    
                    if (!$salaryDelivery) {
                        // إنشاء سجل تسليم جديد
                        $salaryDelivery = $employee->createSalaryDelivery($dateString, $dayHours, $dayAmount);
                    }

                    // تسليم الراتب إذا لم يتم تسليمه
                    if (!$salaryDelivery->isDelivered()) {
                        // تحديث البيانات أولاً
                        $salaryDelivery->update([
                            'hours_worked' => $dayHours,
                            'hourly_rate' => $employee->hourly_rate,
                            'total_amount' => $dayAmount
                        ]);
                        
                        // تحديد كمسلم
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
                            'reason' => 'تم تسليمه مسبقاً'
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
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:1000',
        ]);

        try {
            $now = Carbon::now();
            $currentHour = $now->hour;
            
            // تحديد التاريخ الصحيح بناءً على الوقت الحالي (نفس منطق حساب الراتب)
            if ($currentHour < 7) {
                $targetDate = $now->copy()->subDay()->toDateString();
            } else {
                $targetDate = $now->copy()->toDateString();
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

            // حساب المبلغ المحدث بعد الخصم
            $todayAmount = $employee->getTodayAmount();
            $discountTotal = $employee->getTodayDiscountTotal();

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
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة الخصم: ' . $e->getMessage()
            ], 500);
        }
    }
} 