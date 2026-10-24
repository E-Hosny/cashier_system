<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'contact_person',
        'phone',
        'email',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(SupplierPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return array{owed: float, paid: float, remaining: float}
     */
    public function accountBalance(): array
    {
        $owed = round((float) $this->purchases()->sum('total_amount'), 2);
        $paid = round((float) $this->payments()->sum('amount'), 2);

        return [
            'owed' => $owed,
            'paid' => $paid,
            'remaining' => round($owed - $paid, 2),
        ];
    }
}
