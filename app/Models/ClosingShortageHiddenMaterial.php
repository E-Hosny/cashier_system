<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingShortageHiddenMaterial extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'product_id',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
