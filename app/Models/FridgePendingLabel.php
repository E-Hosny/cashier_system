<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FridgePendingLabel extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    protected $fillable = [
        'tenant_id',
        'fridge_product_config_id',
        'product_id',
        'size',
        'label_code',
        'unit_count',
        'status',
        'branch_id',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_count' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FridgeProductConfig::class, 'fridge_product_config_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
