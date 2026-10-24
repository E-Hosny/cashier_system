<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPurchaseLine extends Model
{
    use BelongsToTenant;

    public const TYPE_RAW = 'raw';

    public const TYPE_CUSTOM = 'custom';

    public const DEST_CENTRAL = 'central';

    public const DEST_BRANCH = 'branch';

    public const DEST_NONE = 'none';

    protected $fillable = [
        'tenant_id',
        'supplier_purchase_id',
        'line_type',
        'product_id',
        'item_name',
        'unit',
        'quantity',
        'unit_price',
        'line_total',
        'stock_destination',
        'branch_id',
        'stock_quantity_added',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'stock_quantity_added' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(SupplierPurchase::class, 'supplier_purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isRaw(): bool
    {
        return $this->line_type === self::TYPE_RAW;
    }
}
