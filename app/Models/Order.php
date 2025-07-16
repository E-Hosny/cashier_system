<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class Order extends Model
{
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

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
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
        static::addGlobalScope('tenant', function (Builder $query) {
            if (auth()->check()) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
                $model->user_id = auth()->id();
            }
        });
    }
}

