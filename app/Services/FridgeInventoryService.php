<?php

namespace App\Services;

use App\Models\BranchRawMaterialStock;
use App\Models\FridgeProductConfig;
use App\Models\FridgeProductIngredientRule;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FridgeInventoryService
{
    /**
     * @return Collection<int, object{raw_material_id: int, quantity_consumed: float}>
     */
    public function ingredientsToDeduct(FridgeProductConfig $config, string $context): Collection
    {
        $size = $config->size ?? '';
        $mode = $context === 'pull' ? $config->deduct_on_pull : $config->deduct_on_sale;

        if ($mode === FridgeProductConfig::MODE_NONE) {
            return collect();
        }

        $base = DB::table('ingredients')
            ->select('raw_material_id', 'quantity_consumed')
            ->where('finished_product_id', $config->product_id)
            ->where('size', $size)
            ->get();

        if ($mode === FridgeProductConfig::MODE_ALL) {
            return $base;
        }

        $rules = $config->relationLoaded('ingredientRules')
            ? $config->ingredientRules
            : $config->ingredientRules()->get();

        $flag = $context === 'pull' ? 'deduct_on_pull' : 'deduct_on_sale';

        $allowedIds = $rules->where($flag, true)->pluck('raw_material_id')->all();

        return $base->whereIn('raw_material_id', $allowedIds)->values();
    }

    /**
     * @return array<int, float> raw_material_id => total consume to deduct
     */
    public function aggregateDeductions(FridgeProductConfig $config, string $context, float $unitCount): array
    {
        $totals = [];
        foreach ($this->ingredientsToDeduct($config, $context) as $row) {
            $id = (int) $row->raw_material_id;
            $totals[$id] = ($totals[$id] ?? 0) + $unitCount * (float) $row->quantity_consumed;
        }

        return $totals;
    }

    public function assertBranchStockAvailable(int $branchId, array $deductions, ?int $tenantId = null): void
    {
        foreach ($deductions as $productId => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $row = BranchRawMaterialStock::query()
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->first();
            $available = (float) ($row?->stock ?? 0);
            if ($available < $amount) {
                $name = DB::table('products')->where('id', $productId)->value('name') ?? 'مادة خام';
                throw new \RuntimeException("مخزون الفرع غير كافٍ للمادة: {$name} (مطلوب ".round($amount, 2).')');
            }
        }
    }

    /**
     * @param  array<int, float>  $deductions
     */
    public function applyBranchDeductions(
        int $branchId,
        array $deductions,
        string $movementType,
        ?int $orderId,
        ?int $tenantId = null
    ): void {
        $movements = [];
        foreach ($deductions as $productId => $amount) {
            if ($amount <= 0) {
                continue;
            }
            BranchRawMaterialStock::deduct($branchId, (int) $productId, $amount, $tenantId);
            $movements[] = [
                'product_id' => (int) $productId,
                'branch_id' => $branchId,
                'quantity' => -$amount,
                'type' => $movementType,
                'related_order_id' => $orderId,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (! empty($movements)) {
            StockMovement::insert($movements);
        }
    }

    /**
     * @param  array<int, array{deduct_on_pull: bool, deduct_on_sale: bool}>  $rulesByMaterialId
     */
    public function syncIngredientRules(FridgeProductConfig $config, array $rulesByMaterialId): void
    {
        $config->ingredientRules()->delete();
        foreach ($rulesByMaterialId as $rawMaterialId => $flags) {
            if (! ($flags['deduct_on_pull'] ?? false) && ! ($flags['deduct_on_sale'] ?? false)) {
                continue;
            }
            FridgeProductIngredientRule::create([
                'fridge_product_config_id' => $config->id,
                'raw_material_id' => (int) $rawMaterialId,
                'deduct_on_pull' => (bool) ($flags['deduct_on_pull'] ?? false),
                'deduct_on_sale' => (bool) ($flags['deduct_on_sale'] ?? false),
            ]);
        }
    }
}
