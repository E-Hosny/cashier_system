<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offer extends Model
{
    use BelongsToTenant;

    public const RULE_FIXED_PRODUCTS = 'fixed_products';

    public const RULE_CATEGORY_PICK = 'category_pick';

    public const RULE_PRODUCT_PICK = 'product_pick';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'offer_price',
        'is_active',
        'priority',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'offer_price' => 'float',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(OfferRule::class)->orderBy('slot_index');
    }

    public function scopeActive($query)
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function toCashierArray(): array
    {
        $this->loadMissing(['rules.products.product:id,name,category_id']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'offer_price' => (float) $this->offer_price,
            'priority' => (int) $this->priority,
            'rules' => $this->rules->map(fn (OfferRule $rule) => $rule->toCashierArray())->values()->all(),
        ];
    }
}
