<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OfflineOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'offline_id',
        'user_id',
        'cashier_shift_id',
        'total',
        'payment_method',
        'status',
        'invoice_number',
        'items',
        'stock_movements',
        'sync_error',
        'synced_at',
        'sync_attempted_at'
    ];

    protected $casts = [
        'items' => 'array',
        'stock_movements' => 'array',
        'total' => 'decimal:2',
        'synced_at' => 'datetime',
        'sync_attempted_at' => 'datetime',
    ];

    /**
     * العلاقة مع المستخدم
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * العلاقة مع وردية الكاشير
     */
    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    /**
     * إنشاء معرف فريد للطلب في وضع عدم الاتصال
     */
    public static function generateOfflineId()
    {
        return 'OFF_' . date('Ymd_His') . '_' . Str::random(8);
    }

    /**
     * الحصول على الطلبات المعلقة للمزامنة
     */
    public static function getPendingSync($userId = null)
    {
        $query = static::where('status', 'pending_sync');
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->orderBy('created_at', 'asc')->get();
    }

    /**
     * الحصول على الطلبات الفاشلة في المزامنة
     */
    public static function getFailedSync($userId = null)
    {
        $query = static::where('status', 'failed');
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->orderBy('sync_attempted_at', 'desc')->get();
    }

    /**
     * تحديث حالة المزامنة
     */
    public function updateSyncStatus($status, $error = null)
    {
        $this->status = $status;
        $this->sync_attempted_at = now();
        
        if ($status === 'synced') {
            $this->synced_at = now();
            $this->sync_error = null;
        } elseif ($status === 'failed') {
            $this->sync_error = $error;
        }
        
        return $this->save();
    }

    /**
     * تحويل الطلب إلى طلب عادي
     */
    public function convertToOrder()
    {
        $orderData = [
            'total' => $this->total,
            'payment_method' => $this->payment_method,
            'status' => 'completed',
            'cashier_shift_id' => $this->cashier_shift_id,
            'invoice_number' => $this->invoice_number,
            'tenant_id' => $this->user->tenant_id,
        ];

        return Order::create($orderData);
    }

    /**
     * إنشاء عناصر الطلب
     */
    public function createOrderItems($orderId)
    {
        $orderItems = [];
        
        foreach ($this->items as $item) {
            $orderItems[] = [
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'size' => $item['size'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        return OrderItem::insert($orderItems);
    }

    /**
     * إنشاء حركات المخزون
     */
    public function createStockMovements($orderId)
    {
        if (empty($this->stock_movements)) {
            return;
        }

        $stockMovements = [];
        
        foreach ($this->stock_movements as $movement) {
            $stockMovements[] = [
                'product_id' => $movement['product_id'],
                'quantity' => $movement['quantity'],
                'type' => $movement['type'],
                'related_order_id' => $orderId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        return StockMovement::insert($stockMovements);
    }

    /**
     * الحصول على إحصائيات الطلبات في وضع عدم الاتصال
     */
    public static function getStats($userId = null)
    {
        $query = static::query();
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return [
            'total' => $query->count(),
            'pending' => $query->where('status', 'pending_sync')->count(),
            'synced' => $query->where('status', 'synced')->count(),
            'failed' => $query->where('status', 'failed')->count(),
            'total_amount' => $query->sum('total'),
        ];
    }
} 