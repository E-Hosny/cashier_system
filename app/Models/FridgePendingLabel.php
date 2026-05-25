<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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

    public function items(): HasMany
    {
        return $this->hasMany(FridgePendingLabelItem::class, 'fridge_pending_label_id');
    }

    public function isCombined(): bool
    {
        return $this->items()->count() > 1;
    }

    /** @return Collection<int, FridgePendingLabelItem> */
    public function resolveLines(): Collection
    {
        $this->loadMissing(['items.product', 'items.config', 'product', 'config']);

        if ($this->items->isNotEmpty()) {
            return $this->items;
        }

        if ($this->fridge_product_config_id) {
            $line = new FridgePendingLabelItem([
                'fridge_product_config_id' => $this->fridge_product_config_id,
                'product_id' => $this->product_id,
                'size' => $this->size ?? '',
                'unit_count' => $this->unit_count,
            ]);
            $line->setRelation('product', $this->product);
            $line->setRelation('config', $this->config);

            return collect([$line]);
        }

        return collect();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
