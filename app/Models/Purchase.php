<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const KIND_RAW = 'raw';

    public const KIND_CUSTOM = 'custom';

    protected $fillable = [
        'tenant_id',
        'purchase_kind',
        'custom_purchase_item_id',
        'supplier_name',
        'description',
        'quantity',
        'total_amount',
        'purchase_date',
    ];

    public function customPurchaseItem(): BelongsTo
    {
        return $this->belongsTo(CustomPurchaseItem::class);
    }

    public function isRaw(): bool
    {
        return ($this->purchase_kind ?? self::KIND_RAW) === self::KIND_RAW;
    }

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}

