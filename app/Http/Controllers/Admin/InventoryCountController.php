<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Services\InventoryCountService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryCountController extends Controller
{
    public function __construct(
        private readonly InventoryCountService $service
    ) {}

    private function requireSuperAdminHub(): void
    {
        $user = auth()->user();
        if (! $user?->hasRole('super admin')) {
            abort(403, 'خاصية الجرد متاحة لسوبر أدمن فقط');
        }

        if (BranchContext::hasBranch()) {
            abort(403, 'افتح الجرد من العرض المركزي للفروع (بدون اختيار فرع في سياق الجلسة).');
        }
    }

    public function index(Request $request)
    {
        $this->requireSuperAdminHub();

        $query = InventoryCount::query()
            ->with(['branch:id,name', 'starter:id,name', 'completer:id,name'])
            ->latest('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        $counts = $query->paginate(20)->withQueryString()->through(fn (InventoryCount $c) => $this->summaryPayload($c));

        return Inertia::render('Admin/RawMaterials/InventoryCounts/Index', [
            'counts' => $counts,
            'hubBranches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'branch_id' => $request->get('branch_id', ''),
                'status' => $request->get('status', ''),
            ],
        ]);
    }

    public function start(Request $request, Branch $branch)
    {
        $this->requireSuperAdminHub();

        $count = $this->service->start($branch, $request->user());

        return redirect()->route('admin.raw-materials.inventory-counts.show', $count);
    }

    public function show(InventoryCount $inventoryCount)
    {
        $this->requireSuperAdminHub();

        $inventoryCount->load([
            'branch:id,name',
            'starter:id,name',
            'completer:id,name',
            'items.product:id,category_id',
            'items.product.category:id,name',
        ]);

        return Inertia::render('Admin/RawMaterials/InventoryCounts/Show', [
            'inventoryCount' => $this->detailPayload($inventoryCount),
            'rawMaterialCategories' => Category::forRawMaterials()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateItem(Request $request, InventoryCount $inventoryCount, InventoryCountItem $item)
    {
        $this->requireSuperAdminHub();

        $data = $request->validate([
            'counted_pieces' => 'nullable|numeric|min:0',
            'counted_qty' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if (! isset($data['counted_pieces']) && ! isset($data['counted_qty'])) {
            return response()->json([
                'success' => false,
                'message' => 'أدخل الكمية الفعلية.',
            ], 422);
        }

        $updated = $this->service->updateItem($inventoryCount, $item, $data);
        $updated->load(['product:id,category_id', 'product.category:id,name']);
        $inventoryCount->refresh();

        return response()->json([
            'success' => true,
            'item' => $this->itemPayload($updated),
            'progress' => [
                'items_count' => $inventoryCount->items_count,
                'counted_items_count' => $inventoryCount->counted_items_count,
            ],
        ]);
    }

    public function clearItem(InventoryCount $inventoryCount, InventoryCountItem $item)
    {
        $this->requireSuperAdminHub();

        $updated = $this->service->clearItem($inventoryCount, $item);
        $updated->load(['product:id,category_id', 'product.category:id,name']);
        $inventoryCount->refresh();

        return response()->json([
            'success' => true,
            'item' => $this->itemPayload($updated),
            'progress' => [
                'items_count' => $inventoryCount->items_count,
                'counted_items_count' => $inventoryCount->counted_items_count,
            ],
        ]);
    }

    public function complete(Request $request, InventoryCount $inventoryCount)
    {
        $this->requireSuperAdminHub();

        $data = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $completed = $this->service->completeAndReconcile(
            $inventoryCount,
            $request->user(),
            $data['notes'] ?? null
        );

        return redirect()
            ->route('admin.raw-materials.inventory-counts.show', $completed)
            ->with('success', 'تم إنهاء الجرد وموازنة مخزون الفرع بنجاح.');
    }

    public function cancel(Request $request, InventoryCount $inventoryCount)
    {
        $this->requireSuperAdminHub();

        $this->service->cancel($inventoryCount, $request->user());

        return redirect()
            ->route('admin.raw-materials.index', [
                'view_scope' => (string) $inventoryCount->branch_id,
                'tab' => 'materials',
            ])
            ->with('success', 'تم إلغاء الجرد دون تعديل المخزون.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryPayload(InventoryCount $c): array
    {
        return [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'branch_name' => $c->branch?->name,
            'status' => $c->status,
            'status_label' => $this->statusLabel($c->status),
            'started_at' => optional($c->started_at)?->format('Y-m-d H:i'),
            'completed_at' => optional($c->completed_at)?->format('Y-m-d H:i'),
            'started_by_name' => $c->starter?->name,
            'completed_by_name' => $c->completer?->name,
            'items_count' => $c->items_count,
            'counted_items_count' => $c->counted_items_count,
            'total_surplus_value' => (float) $c->total_surplus_value,
            'total_shortage_value' => (float) $c->total_shortage_value,
            'net_diff_value' => (float) $c->net_diff_value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(InventoryCount $c): array
    {
        $items = $c->items
            ->sortBy('product_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (InventoryCountItem $item) => $this->itemPayload($item));

        $liveSurplusValue = 0.0;
        $liveShortageValue = 0.0;
        $liveSurplusQty = 0.0;
        $liveShortageQty = 0.0;
        foreach ($c->items->where('is_counted', true) as $item) {
            $diffQty = (float) $item->diff_qty;
            $diffValue = (float) $item->diff_value;
            if ($diffQty > 0.0001) {
                $liveSurplusQty += $diffQty;
                $liveSurplusValue += $diffValue;
            } elseif ($diffQty < -0.0001) {
                $liveShortageQty += abs($diffQty);
                $liveShortageValue += abs($diffValue);
            }
        }

        return [
            'id' => $c->id,
            'branch_id' => $c->branch_id,
            'branch_name' => $c->branch?->name,
            'status' => $c->status,
            'status_label' => $this->statusLabel($c->status),
            'is_in_progress' => $c->isInProgress(),
            'is_completed' => $c->isCompleted(),
            'started_at' => optional($c->started_at)?->format('Y-m-d H:i'),
            'completed_at' => optional($c->completed_at)?->format('Y-m-d H:i'),
            'started_by_name' => $c->starter?->name,
            'completed_by_name' => $c->completer?->name,
            'notes' => $c->notes,
            'items_count' => $c->items_count,
            'counted_items_count' => $c->counted_items_count,
            'total_surplus_qty' => (float) ($c->isCompleted() ? $c->total_surplus_qty : $liveSurplusQty),
            'total_shortage_qty' => (float) ($c->isCompleted() ? $c->total_shortage_qty : $liveShortageQty),
            'total_surplus_value' => (float) ($c->isCompleted() ? $c->total_surplus_value : $liveSurplusValue),
            'total_shortage_value' => (float) ($c->isCompleted() ? $c->total_shortage_value : $liveShortageValue),
            'net_diff_value' => (float) ($c->isCompleted()
                ? $c->net_diff_value
                : ($liveSurplusValue - $liveShortageValue)),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(InventoryCountItem $item): array
    {
        $qpu = $item->quantityPerUnit();
        $systemPieces = $item->systemPieces();
        $countedPieces = $item->countedPieces();
        $diffQty = $item->diff_qty !== null ? (float) $item->diff_qty : null;
        $diffPieces = $diffQty !== null ? round($diffQty / $qpu, 4) : null;

        return [
            'id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'category_id' => $item->product?->category_id ? (int) $item->product->category_id : null,
            'category_name' => $item->product?->category?->name,
            'unit' => $item->unit,
            'consume_unit' => $item->consume_unit,
            'quantity_per_unit' => (float) $qpu,
            'system_qty' => (float) $item->system_qty,
            'system_pieces' => $systemPieces,
            'counted_qty' => $item->counted_qty !== null ? (float) $item->counted_qty : null,
            'counted_pieces' => $countedPieces,
            'diff_qty' => $diffQty,
            'diff_pieces' => $diffPieces,
            'unit_cost' => (float) $item->unit_cost,
            'system_value' => round((float) $item->system_qty * (float) $item->unit_cost, 2),
            'counted_value' => $item->counted_qty !== null
                ? round((float) $item->counted_qty * (float) $item->unit_cost, 2)
                : null,
            'diff_value' => $item->diff_value !== null ? (float) $item->diff_value : null,
            'is_counted' => (bool) $item->is_counted,
            'counted_at' => optional($item->counted_at)?->format('Y-m-d H:i'),
            'note' => $item->note,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            InventoryCount::STATUS_IN_PROGRESS => 'قيد التنفيذ',
            InventoryCount::STATUS_COMPLETED => 'مكتمل ومُوازن',
            InventoryCount::STATUS_CANCELLED => 'ملغي',
            default => $status,
        };
    }
}
