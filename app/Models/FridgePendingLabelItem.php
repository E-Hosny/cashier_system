<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FridgePendingLabelItem extends Model
{
    protected $fillable = [
        'fridge_pending_label_id',
        'fridge_product_config_id',
        'product_id',
        'size',
        'unit_count',
    ];

    protected function casts(): array
    {
        return ['unit_count' => 'decimal:4'];
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(FridgePendingLabel::class, 'fridge_pending_label_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FridgeProductConfig::class, 'fridge_product_config_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
