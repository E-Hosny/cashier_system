<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RawMaterialPendingLabelItem extends Model
{
    protected $fillable = [
        'raw_material_pending_label_id',
        'product_id',
        'piece_count',
        'consume_amount',
        'source_label_id',
    ];

    protected function casts(): array
    {
        return [
            'piece_count' => 'decimal:4',
            'consume_amount' => 'decimal:4',
        ];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(RawMaterialPendingLabel::class, 'raw_material_pending_label_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sourceLabel(): BelongsTo
    {
        return $this->belongsTo(RawMaterialPendingLabel::class, 'source_label_id');
    }
}
