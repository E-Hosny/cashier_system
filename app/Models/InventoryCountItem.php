<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCountItem extends Model
{
    protected $fillable = [
        'inventory_count_id',
        'product_id',
        'product_name',
        'unit',
        'consume_unit',
        'quantity_per_unit',
        'system_qty',
        'counted_qty',
        'diff_qty',
        'unit_cost',
        'diff_value',
        'is_counted',
        'counted_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per_unit' => 'decimal:4',
            'system_qty' => 'decimal:4',
            'counted_qty' => 'decimal:4',
            'diff_qty' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'diff_value' => 'decimal:2',
            'is_counted' => 'boolean',
            'counted_at' => 'datetime',
        ];
    }

    public function inventoryCount(): BelongsTo
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function quantityPerUnit(): float
    {
        $qpu = (float) ($this->quantity_per_unit ?: 0);

        return $qpu > 0 ? $qpu : 1.0;
    }

    public function systemPieces(): float
    {
        return round((float) $this->system_qty / $this->quantityPerUnit(), 4);
    }

    public function countedPieces(): ?float
    {
        if ($this->counted_qty === null) {
            return null;
        }

        return round((float) $this->counted_qty / $this->quantityPerUnit(), 4);
    }
}
