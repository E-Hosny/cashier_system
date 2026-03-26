<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'type',
        'related_order_id',
        'related_purchase_id',
        'tenant_id',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }
} 