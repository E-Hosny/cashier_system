<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'total',
        'status',
        'payment_method',
        'tenant_id',
        'user_id',
        'cashier_shift_id',
        'invoice_number'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * العلاقة مع وردية الكاشير
     */
    public function cashierShift()
    {
        return $this->belongsTo(CashierShift::class);
    }

    protected static function booted()
    {
        static::bootBelongsToTenant();
        static::creating(function ($model) {
            if (auth()->check()) {
                $model->user_id = $model->user_id ?? auth()->id();
            }
        });
    }
}

