<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Inertia\Inertia;

class CashierShiftController extends Controller
{
    /**
     * بدء وردية جديدة
     */
    public function startShift(Request $request)
    {
        $request->validate([
            'shift_type' => 'required|in:morning,evening',
        ]);

        $user = Auth::user();
        
        // التحقق من عدم وجود وردية نشطة
        $activeShift = CashierShift::getActiveShift($user->id);
        if ($activeShift) {
            return response()->json([
                'success' => false,
                'message' => 'لديك وردية نشطة بالفعل. يجب إغلاقها أولاً.'
            ], 400);
        }

        // إنشاء وردية جديدة
        $shift = CashierShift::create([
            'user_id' => $user->id,
            'shift_type' => $request->shift_type,
            'start_time' => now(),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم بدء الوردية بنجاح',
            'shift' => $shift
        ]);
    }

    /**
     * إغلاق الوردية
     */
    public function closeShift(Request $request)
    {
        $request->validate([
            'cash_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $shift = CashierShift::getActiveShift($user->id);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد وردية نشطة لإغلاقها'
            ], 400);
        }

        // إغلاق الوردية
        $shift->closeShift($request->cash_amount, $request->notes);

        // الحصول على تفاصيل المبيعات
        $salesDetails = $shift->getSalesDetails();

        return response()->json([
            'success' => true,
            'message' => 'تم إغلاق الوردية بنجاح',
            'shift' => $shift->fresh(),
            'sales_details' => $salesDetails
        ]);
    }

    /**
     * تسليم الوردية
     */
    public function handOverShift(Request $request)
    {
        $user = Auth::user();
        $shift = CashierShift::where('user_id', $user->id)
            ->where('status', 'closed')
            ->orderBy('end_time', 'desc')
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد وردية مغلقة لتسليمها'
            ], 400);
        }

        // تسليم الوردية
        $shift->handOverShift();

        return response()->json([
            'success' => true,
            'message' => 'تم تسليم الوردية بنجاح'
        ]);
    }

    /**
     * تحديث المبلغ النقدي للوردية
     */
    public function updateCashAmount(Request $request, $shiftId)
    {
        $request->validate([
            'cash_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $shift = CashierShift::where('id', $shiftId)
            ->where('user_id', $user->id)
            ->where('status', 'closed')
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'الوردية غير موجودة أو لا يمكن تحديثها'
            ], 404);
        }

        // تحديث المبلغ النقدي
        $shift->update([
            'cash_amount' => $request->cash_amount,
            'notes' => $request->notes,
            'difference' => $request->cash_amount - $shift->expected_amount
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المبلغ النقدي بنجاح',
            'shift' => $shift->fresh()
        ]);
    }

    /**
     * الحصول على تفاصيل الوردية الحالية
     */
    public function getCurrentShift()
    {
        $user = Auth::user();
        $shift = CashierShift::getActiveShift($user->id);

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد وردية نشطة'
            ], 404);
        }

        // حساب المبيعات الحالية
        $currentSales = $shift->calculateTotalSales();
        $expectedAmount = $shift->calculateExpectedAmount();

        return response()->json([
            'success' => true,
            'shift' => $shift,
            'current_sales' => $currentSales,
            'expected_amount' => $expectedAmount
        ]);
    }

    /**
     * الحصول على تفاصيل المبيعات للوردية المغلقة
     */
    public function getShiftDetails(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|exists:cashier_shifts,id'
        ]);

        $shift = CashierShift::with('user')->findOrFail($request->shift_id);
        
        // التحقق من أن المستخدم يمكنه الوصول لهذه الوردية
        if ($shift->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول لهذه الوردية'
            ], 403);
        }

        $salesDetails = $shift->getSalesDetails();
        $salesSummary = $shift->getSalesSummary();

        return response()->json([
            'success' => true,
            'shift' => $shift,
            'sales_details' => $salesDetails,
            'sales_summary' => $salesSummary
        ]);
    }

    /**
     * الحصول على تاريخ الورديات للمستخدم
     */
    public function getShiftHistory(Request $request)
    {
        $user = Auth::user();
        
        $shifts = CashierShift::where('user_id', $user->id)
            ->orderBy('start_time', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'shifts' => $shifts
        ]);
    }

    /**
     * الحصول على إحصائيات الوردية
     */
    public function getShiftStats()
    {
        $user = Auth::user();
        $today = Carbon::today();
        
        // إحصائيات اليوم
        $todayShifts = CashierShift::where('user_id', $user->id)
            ->whereDate('start_time', $today)
            ->get();

        $totalTodaySales = $todayShifts->sum('total_sales');
        $totalTodayCash = $todayShifts->sum('cash_amount');
        $totalTodayExpected = $todayShifts->sum('expected_amount');
        $totalTodayDifference = $todayShifts->sum('difference');

        // إحصائيات الشهر
        $monthStart = Carbon::now()->startOfMonth();
        $monthShifts = CashierShift::where('user_id', $user->id)
            ->where('start_time', '>=', $monthStart)
            ->get();

        $totalMonthSales = $monthShifts->sum('total_sales');
        $totalMonthCash = $monthShifts->sum('cash_amount');
        $totalMonthExpected = $monthShifts->sum('expected_amount');
        $totalMonthDifference = $monthShifts->sum('difference');

        return response()->json([
            'success' => true,
            'stats' => [
                'today' => [
                    'shifts_count' => $todayShifts->count(),
                    'total_sales' => $totalTodaySales,
                    'total_cash' => $totalTodayCash,
                    'total_expected' => $totalTodayExpected,
                    'total_difference' => $totalTodayDifference,
                ],
                'month' => [
                    'shifts_count' => $monthShifts->count(),
                    'total_sales' => $totalMonthSales,
                    'total_cash' => $totalMonthCash,
                    'total_expected' => $totalMonthExpected,
                    'total_difference' => $totalMonthDifference,
                ]
            ]
        ]);
    }
} 