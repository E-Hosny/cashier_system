<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAddonGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'required_count',
        'size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'required_count' => 'integer',
            'sort_order' => 'integer',
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

    public function options(): HasMany
    {
        return $this->hasMany(ProductAddonOption::class, 'group_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * هل تنطبق هذه المجموعة على حجم المنتج المختار؟
     */
    public function appliesToSize(?string $size): bool
    {
        if ($this->size === null || $this->size === '') {
            return true;
        }

        return (string) $this->size === (string) ($size ?? '');
    }
}
