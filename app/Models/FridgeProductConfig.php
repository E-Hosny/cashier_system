<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FridgeProductConfig extends Model
{
    use BelongsToTenant;

    public const MODE_NONE = 'none';

    public const MODE_ALL = 'all';

    public const MODE_CUSTOM = 'custom';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'size',
        'deduct_on_pull',
        'deduct_on_sale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredientRules(): HasMany
    {
        return $this->hasMany(FridgeProductIngredientRule::class);
    }

    public function pendingLabels(): HasMany
    {
        return $this->hasMany(FridgePendingLabel::class);
    }
}
