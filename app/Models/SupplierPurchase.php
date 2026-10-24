<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPurchase extends Model
{
    use BelongsToTenant;

    public const DISK = 'spaces';

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'purchased_on',
        'invoice_number',
        'invoice_path',
        'invoice_disk',
        'invoice_original_name',
        'invoice_mime',
        'notes',
        'total_amount',
        'created_by',
    ];

    protected $casts = [
        'purchased_on' => 'date',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierPurchaseLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
