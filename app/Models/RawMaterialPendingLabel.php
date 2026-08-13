<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class RawMaterialPendingLabel extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_BUNDLED = 'bundled';

    protected $table = 'raw_material_pending_labels';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'branch_id',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RawMaterialPendingLabelItem::class, 'raw_material_pending_label_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCombined(): bool
    {
        return $this->items()->count() > 1;
    }

    /** @return Collection<int, RawMaterialPendingLabelItem> */
    public function resolveLines(): Collection
    {
        $this->loadMissing(['items.product', 'product']);

        if ($this->items->isNotEmpty()) {
            return $this->items;
        }

        if ($this->product_id) {
            $line = new RawMaterialPendingLabelItem([
                'product_id' => $this->product_id,
                'piece_count' => $this->piece_count,
                'consume_amount' => $this->consume_amount,
            ]);
            $line->setRelation('product', $this->product);

            return collect([$line]);
        }

        return collect();
    }
}
