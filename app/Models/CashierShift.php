<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shift_type',
        'start_time',
        'end_time',
        'total_sales',
        'cash_amount',
        'expected_amount',
        'difference',
        'notes',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'total_sales' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    /**
     * العلاقة مع المستخدم
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * الحصول على الوردية النشطة للمستخدم الحالي
     */
    public static function getActiveShift($userId)
    {
        return static::where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * الحصول على آخر وردية مغلقة للمستخدم
     */
    public static function getLastClosedShift($userId)
    {
        return static::where('user_id', $userId)
            ->where('status', 'handed_over')
            ->orderBy('end_time', 'desc')
            ->first();
    }

    /**
     * حساب إجمالي المبيعات للوردية
     */
    public function calculateTotalSales()
    {
        return Order::where('cashier_shift_id', $this->id)->sum('total');
    }

    /**
     * حساب المبلغ المتوقع حسب النظام
     */
    public function calculateExpectedAmount()
    {
        // المبلغ المتوقع هو نفس إجمالي المبيعات للوردية الحالية
        return $this->calculateTotalSales();
    }

    /**
     * إغلاق الوردية
     */
    public function closeShift($cashAmount, $notes = null)
    {
        $this->end_time = now();
        $this->total_sales = $this->calculateTotalSales();
        $this->expected_amount = $this->calculateExpectedAmount();
        $this->cash_amount = $cashAmount;
        $this->difference = $cashAmount - $this->expected_amount;
        $this->notes = $notes;
        $this->status = 'closed';
        
        return $this->save();
    }

    /**
     * تسليم الوردية
     */
    public function handOverShift()
    {
        $this->status = 'handed_over';
        return $this->save();
    }

    /**
     * الحصول على تفاصيل المبيعات للوردية
     */
    public function getSalesDetails()
    {
        return Order::with('items')
            ->where('cashier_shift_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * الحصول على ملخص المبيعات حسب المنتج
     */
    public function getSalesSummary()
    {
        return OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw('
                order_items.product_name,
                order_items.size,
                SUM(order_items.quantity) as total_quantity,
                SUM(order_items.quantity * order_items.price) as total_amount
            ')
            ->where('orders.cashier_shift_id', $this->id)
            ->groupBy('order_items.product_name', 'order_items.size')
            ->orderBy('total_amount', 'desc')
            ->get();
    }
} 