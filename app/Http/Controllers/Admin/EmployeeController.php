<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
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

            // إضافة معلومات الحضور الحالية لكل موظف
    $employees->each(function ($employee) {
        $employee->current_attendance = $employee->getCurrentAttendance();
        $employee->is_present = $employee->isCurrentlyPresent();
        $employee->today_hours = $employee->getTodayHours();
        $employee->today_amount = $employee->getTodayAmount();
        $employee->today_attendance_records = $employee->getTodayAttendanceRecords();
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

            $dailyDetails[] = [
                'date' => $currentDate->format('Y-m-d'),
                'date_arabic' => $currentDate->format('d/m/Y'),
                'day_name' => $currentDate->locale('ar')->dayName,
                'hours' => round($dayHours, 2),
                'amount' => round($dayAmount, 2),
                'records' => $dayRecords,
                'has_records' => count($dayRecords) > 0,
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
} 