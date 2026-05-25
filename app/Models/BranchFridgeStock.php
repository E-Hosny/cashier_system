<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchFridgeStock extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'product_id',
        'size',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
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

    public static function adjust(int $branchId, int $productId, string $size, float $delta, ?int $tenantId = null): self
    {
        $tenantId = $tenantId ?? auth()->user()?->tenant_id;
        $size = $size ?? '';

        $row = static::firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId, 'size' => $size],
            ['tenant_id' => $tenantId, 'quantity' => 0]
        );

        $row->increment('quantity', $delta);

        return $row->fresh();
    }

    public static function deduct(int $branchId, int $productId, string $size, float $amount, ?int $tenantId = null): self
    {
        $row = static::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('size', $size ?? '')
            ->first();

        $available = (float) ($row?->quantity ?? 0);
        if ($available < $amount) {
            throw new \RuntimeException('مخزون التلاجة غير كافٍ لهذا المنتج.');
        }

        return static::adjust($branchId, $productId, $size, -abs($amount), $tenantId);
    }
}
