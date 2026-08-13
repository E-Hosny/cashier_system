<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchRawMaterialStock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'product_id',
        'stock',
        'stock_alert_threshold',
    ];

    protected function casts(): array
    {
        return [
            'stock' => 'decimal:4',
            'stock_alert_threshold' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function adjust(int $branchId, int $productId, float $delta, ?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;

        $row = static::firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['tenant_id' => $tenantId, 'stock' => 0]
        );

        $row->increment('stock', $delta);

        return $row->fresh();
    }

    /** خصم كمية من مخزون الفرع (وحدة الاستهلاك). */
    public static function deduct(int $branchId, int $productId, float $amount, ?int $tenantId = null): self
    {
        return static::adjust($branchId, $productId, -abs($amount), $tenantId);
    }

    /**
     * تعيين كمية مطلقة لمخزون الفرع مع تسجيل حركة.
     *
     * @return array{row: self, old: float, new: float, delta: float}
     */
    public static function setAbsolute(
        int $branchId,
        int $productId,
        float $newStock,
        ?int $tenantId = null,
        string $movementType = 'branch_manual_adjustment'
    ): array {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        $newStock = max(0, $newStock);

        $row = static::query()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            ['tenant_id' => $tenantId, 'stock' => 0]
        );

        $old = (float) $row->stock;
        $delta = $newStock - $old;

        if (abs($delta) > 0.0001) {
            $row->update([
                'stock' => $newStock,
                'tenant_id' => $row->tenant_id ?? $tenantId,
            ]);

            StockMovement::create([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'quantity' => $delta,
                'type' => $movementType,
                'tenant_id' => $row->tenant_id ?? $tenantId,
            ]);
        }

        return [
            'row' => $row->fresh(),
            'old' => $old,
            'new' => $newStock,
            'delta' => $delta,
        ];
    }
}
