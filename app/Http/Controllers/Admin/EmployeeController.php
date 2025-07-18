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
} 