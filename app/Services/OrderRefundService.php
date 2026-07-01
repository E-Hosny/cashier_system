<?php

namespace App\Services;

use App\Models\BranchFridgeStock;
use App\Models\BranchRawMaterialStock;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class OrderRefundService
{
    public function canRefund(Order $order): bool
    {
        return $order->status === 'completed' && $order->refunded_at === null;
    }

    /**
     * إرجاع فاتورة وإعادة المخزون (مواد خام + تلاجة).
     *
     * @throws \RuntimeException
     */
    public function refund(Order $order, ?int $userId = null): Order
    {
        if (! $this->canRefund($order)) {
            throw new \RuntimeException('تم إرجاع هذه الفاتورة مسبقاً أو لا يمكن إرجاعها.');
        }

        $branchId = (int) $order->branch_id;
        $tenantId = (int) $order->tenant_id;

        if ($branchId <= 0) {
            throw new \RuntimeException('الفاتورة غير مرتبطة بفرع.');
        }

        DB::transaction(function () use ($order, $branchId, $tenantId, $userId) {
            $order->load('items');

            $movements = StockMovement::withoutGlobalScopes()
                ->where('related_order_id', $order->id)
                ->whereIn('type', ['sale_deduction', 'fridge_sale_ingredient'])
                ->get();

            $refundMovements = [];
            $now = now();

            foreach ($movements as $movement) {
                $amount = abs((float) $movement->quantity);
                if ($amount <= 0) {
                    continue;
                }

                BranchRawMaterialStock::adjust(
                    $branchId,
                    (int) $movement->product_id,
                    $amount,
                    $tenantId
                );

                $refundMovements[] = [
                    'product_id' => $movement->product_id,
                    'branch_id' => $branchId,
                    'quantity' => $amount,
                    'type' => $movement->type === 'fridge_sale_ingredient'
                        ? 'fridge_sale_refund'
                        : 'sale_refund',
                    'related_order_id' => $order->id,
                    'tenant_id' => $tenantId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($order->items as $item) {
                if (! $item->from_fridge) {
                    continue;
                }

                BranchFridgeStock::adjust(
                    $branchId,
                    (int) $item->product_id,
                    (string) ($item->size ?? ''),
                    (float) $item->quantity,
                    $tenantId
                );
            }

            if ($refundMovements !== []) {
                StockMovement::insert($refundMovements);
            }

            $order->update([
                'status' => 'refunded',
                'refunded_at' => $now,
                'refunded_by' => $userId,
            ]);
        });

        return $order->fresh(['items']);
    }
}
