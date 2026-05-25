<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FridgeProductIngredientRule extends Model
{
    protected $fillable = [
        'fridge_product_config_id',
        'raw_material_id',
        'deduct_on_pull',
        'deduct_on_sale',
    ];

    protected function casts(): array
    {
        return [
            'deduct_on_pull' => 'boolean',
            'deduct_on_sale' => 'boolean',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FridgeProductConfig::class, 'fridge_product_config_id');
    }

    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'raw_material_id');
    }
}
