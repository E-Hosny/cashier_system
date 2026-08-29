<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfferRule extends Model
{
    protected $fillable = [
        'offer_id',
        'slot_index',
        'rule_type',
        'quantity',
        'category_id',
    ];

    protected $casts = [
        'slot_index' => 'integer',
        'quantity' => 'integer',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(OfferRuleProduct::class);
    }

    public function toCashierArray(): array
    {
        $this->loadMissing('products');

        return [
            'slot_index' => $this->slot_index,
            'rule_type' => $this->rule_type,
            'quantity' => (int) $this->quantity,
            'category_id' => $this->category_id,
            'products' => $this->products->map(fn (OfferRuleProduct $p) => [
                'product_id' => $p->product_id,
                'quantity' => (int) $p->quantity,
                'size' => $p->size,
            ])->values()->all(),
        ];
    }
}
