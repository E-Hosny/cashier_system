<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialPendingLabel extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    protected $table = 'raw_material_pending_labels';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'label_code',
        'piece_count',
        'consume_amount',
        'status',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'piece_count' => 'decimal:4',
            'consume_amount' => 'decimal:4',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
