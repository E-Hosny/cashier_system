<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRuleProduct extends Model
{
    protected $fillable = [
        'offer_rule_id',
        'product_id',
        'quantity',
        'size',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(OfferRule::class, 'offer_rule_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
