<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchRawMaterialStock;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCountService
{
    public function findOpenForBranch(int $branchId): ?InventoryCount
    {
        return InventoryCount::query()
            ->where('branch_id', $branchId)
            ->where('status', InventoryCount::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();
    }

    public function start(Branch $branch, User $user): InventoryCount
    {
        $existing = $this->findOpenForBranch($branch->id);
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($branch, $user) {
            $products = Product::query()
                ->where('type', 'raw')
                ->orderBy('name')
                ->get();

            $stocks = BranchRawMaterialStock::query()
                ->where('branch_id', $branch->id)
                ->get()
                ->keyBy('product_id');

            $count = InventoryCount::create([
                'tenant_id' => $branch->tenant_id ?? $user->tenant_id,
                'branch_id' => $branch->id,
                'status' => InventoryCount::STATUS_IN_PROGRESS,
                'started_by' => $user->id,
                'started_at' => now(),
                'items_count' => $products->count(),
                'counted_items_count' => 0,
            ]);

            $rows = [];
            $now = now();
            foreach ($products as $product) {
                $systemQty = (float) ($stocks[$product->id]->stock ?? 0);
                $unitCost = (float) ($product->unit_consume_price ?: 0);
                if ($unitCost <= 0 && (float) ($product->quantity_per_unit ?: 0) > 0 && (float) ($product->purchase_price ?: 0) > 0) {
                    $unitCost = (float) $product->purchase_price / (float) $product->quantity_per_unit;
                }

                $rows[] = [
                    'inventory_count_id' => $count->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit' => $product->unit,
                    'consume_unit' => $product->consume_unit,
                    'quantity_per_unit' => $product->quantity_per_unit,
                    'system_qty' => $systemQty,
                    'counted_qty' => null,
                    'diff_qty' => null,
                    'unit_cost' => $unitCost,
                    'diff_value' => null,
                    'is_counted' => false,
                    'counted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($rows !== []) {
                foreach (array_chunk($rows, 200) as $chunk) {
                    InventoryCountItem::query()->insert($chunk);
                }
            }

            return $count->fresh(['items', 'branch', 'starter']);
        });
    }

    /**
     * @param  array{counted_pieces?: float|int|string|null, counted_qty?: float|int|string|null, note?: string|null}  $data
     */
    public function updateItem(InventoryCount $count, InventoryCountItem $item, array $data): InventoryCountItem
    {
        if (! $count->isInProgress()) {
            throw ValidationException::withMessages([
                'inventory' => 'لا يمكن تعديل جرد مكتمل أو ملغي.',
            ]);
        }

        if ((int) $item->inventory_count_id !== (int) $count->id) {
            abort(404);
        }

        $qpu = $item->quantityPerUnit();
        if (array_key_exists('counted_qty', $data) && $data['counted_qty'] !== null && $data['counted_qty'] !== '') {
            $countedQty = max(0, (float) $data['counted_qty']);
        } elseif (array_key_exists('counted_pieces', $data) && $data['counted_pieces'] !== null && $data['counted_pieces'] !== '') {
            $countedQty = max(0, (float) $data['counted_pieces'] * $qpu);
        } else {
            throw ValidationException::withMessages([
                'counted_pieces' => 'أدخل الكمية الفعلية.',
            ]);
        }

        $diffQty = $countedQty - (float) $item->system_qty;
        $diffValue = round($diffQty * (float) $item->unit_cost, 2);

        $item->update([
            'counted_qty' => $countedQty,
            'diff_qty' => $diffQty,
            'diff_value' => $diffValue,
            'is_counted' => true,
            'counted_at' => now(),
            'note' => $data['note'] ?? $item->note,
        ]);

        $this->refreshProgress($count);

        return $item->fresh();
    }

    public function clearItem(InventoryCount $count, InventoryCountItem $item): InventoryCountItem
    {
        if (! $count->isInProgress()) {
            throw ValidationException::withMessages([
                'inventory' => 'لا يمكن تعديل جرد مكتمل أو ملغي.',
            ]);
        }

        if ((int) $item->inventory_count_id !== (int) $count->id) {
            abort(404);
        }

        $item->update([
            'counted_qty' => null,
            'diff_qty' => null,
            'diff_value' => null,
            'is_counted' => false,
            'counted_at' => null,
        ]);

        $this->refreshProgress($count);

        return $item->fresh();
    }

    public function refreshProgress(InventoryCount $count): void
    {
        $counted = $count->items()->where('is_counted', true)->count();
        $count->update([
            'counted_items_count' => $counted,
            'items_count' => $count->items()->count(),
        ]);
    }

    /**
     * إنهاء الجرد وموازنة مخزون الفرع حسب الكميات المعدودة.
     * البنود غير المعدودة تُعامل كأنها مطابقة للنظام (بدون تغيير).
     */
    public function completeAndReconcile(InventoryCount $count, User $user, ?string $notes = null): InventoryCount
    {
        if (! $count->isInProgress()) {
            throw ValidationException::withMessages([
                'inventory' => 'هذا الجرد ليس قيد التنفيذ.',
            ]);
        }

        return DB::transaction(function () use ($count, $user, $notes) {
            $count->load('items');

            $surplusQty = 0.0;
            $shortageQty = 0.0;
            $surplusValue = 0.0;
            $shortageValue = 0.0;

            foreach ($count->items as $item) {
                $finalQty = $item->is_counted
                    ? (float) $item->counted_qty
                    : (float) $item->system_qty;

                if (! $item->is_counted) {
                    $item->update([
                        'counted_qty' => $finalQty,
                        'diff_qty' => 0,
                        'diff_value' => 0,
                        'is_counted' => true,
                        'counted_at' => $item->counted_at ?? now(),
                        'note' => $item->note ?: 'لم يُعدّ — اعتُبر مطابقاً للنظام عند الإنهاء',
                    ]);
                }

                $diffQty = $finalQty - (float) $item->system_qty;
                $diffValue = round($diffQty * (float) $item->unit_cost, 2);

                $item->update([
                    'diff_qty' => $diffQty,
                    'diff_value' => $diffValue,
                ]);

                BranchRawMaterialStock::setAbsolute(
                    (int) $count->branch_id,
                    (int) $item->product_id,
                    $finalQty,
                    $count->tenant_id,
                    'inventory_count'
                );

                if ($diffQty > 0.0001) {
                    $surplusQty += $diffQty;
                    $surplusValue += $diffValue;
                } elseif ($diffQty < -0.0001) {
                    $shortageQty += abs($diffQty);
                    $shortageValue += abs($diffValue);
                }
            }

            $count->update([
                'status' => InventoryCount::STATUS_COMPLETED,
                'completed_by' => $user->id,
                'completed_at' => now(),
                'notes' => $notes,
                'counted_items_count' => $count->items()->where('is_counted', true)->count(),
                'items_count' => $count->items()->count(),
                'total_surplus_qty' => round($surplusQty, 4),
                'total_shortage_qty' => round($shortageQty, 4),
                'total_surplus_value' => round($surplusValue, 2),
                'total_shortage_value' => round($shortageValue, 2),
                'net_diff_value' => round($surplusValue - $shortageValue, 2),
            ]);

            return $count->fresh(['items', 'branch', 'starter', 'completer']);
        });
    }

    public function cancel(InventoryCount $count, User $user): InventoryCount
    {
        if (! $count->isInProgress()) {
            throw ValidationException::withMessages([
                'inventory' => 'لا يمكن إلغاء جرد غير قيد التنفيذ.',
            ]);
        }

        $count->update([
            'status' => InventoryCount::STATUS_CANCELLED,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        return $count->fresh();
    }
}
