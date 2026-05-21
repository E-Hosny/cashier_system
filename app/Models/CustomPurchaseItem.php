<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomPurchaseItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'unit',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
