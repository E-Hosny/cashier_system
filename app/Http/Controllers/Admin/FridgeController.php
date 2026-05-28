<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchFridgeStock;
use App\Models\Category;
use App\Models\Employee;
use App\Models\FridgePendingLabel;
use App\Models\FridgePendingLabelItem;
use App\Models\FridgeProductConfig;
use App\Models\Product;
use App\Services\FridgeInventoryService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FridgeController extends Controller
{
    public function __construct(
        private FridgeInventoryService $fridgeService
    ) {}

    private function requireHubRoles(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['admin', 'super admin'])) {
            abort(403);
        }
    }

    private function isCentralHub(): bool
    {
        return auth()->user()?->hasRole('super admin') && ! BranchContext::hasBranch();
    }

    private function generateFridgeLabelCode(): string
    {
        // Keep barcode short for reliable scanner reading on small stickers.
        for ($i = 0; $i < 10; $i++) {
            $code = 'FR'.strtoupper(Str::random(8));
            $exists = FridgePendingLabel::query()->where('label_code', $code)->exists();
            if (! $exists) {
                return $code;
            }
        }

        return 'FR'.strtoupper(Str::random(12));
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildIndexPayload(?int $branchId = null): array
    {
        $configs = FridgeProductConfig::query()
            ->with(['product:id,name,size_variants,type', 'ingredientRules.rawMaterial:id,name,consume_unit'])
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $fridgeService = app(FridgeInventoryService::class);

        $configs = $configs->map(function (FridgeProductConfig $c) use ($fridgeService) {
                $variants = $c->product?->size_variants ?? [];
                $sizeLabel = $c->size !== '' ? $c->size : (count($variants) ? null : '—');

                return [
                    'id' => $c->id,
                    'product_id' => $c->product_id,
                    'product_name' => $c->product?->name ?? '—',
                    'size' => $c->size,
                    'size_label' => $sizeLabel,
                    'deduct_on_sale' => $c->deduct_on_sale,
                    'ingredient_rules' => $c->ingredientRules->map(fn ($r) => [
                        'raw_material_id' => $r->raw_material_id,
                        'name' => $r->rawMaterial?->name,
                        'deduct_on_sale' => $r->deduct_on_sale,
                    ])->values()->all(),
                    'sale_ingredients' => $fridgeService->saleIngredientsForDisplay($c),
                ];
            });

        $pendingSums = self::pendingUnitsByConfigId();

        $configs = $configs->map(function (array $row) use ($pendingSums) {
            $row['pending_units'] = (float) ($pendingSums[$row['id']] ?? 0);

            return $row;
        });

        $categories = Category::forProducts()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]);

        $finishedProducts = Product::query()
            ->where('type', 'finished')
            ->published()
            ->orderBy('name')
            ->get(['id', 'name', 'category_id', 'size_variants'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category_id' => $p->category_id,
                'sizes' => collect($p->size_variants ?? [])->pluck('size')->filter()->values()->all(),
            ]);

        $mapStockRow = fn (BranchFridgeStock $s) => [
            'product_id' => $s->product_id,
            'product_name' => $s->product?->name ?? '—',
            'size' => $s->size,
            'quantity' => (float) $s->quantity,
        ];

        $stocks = [];
        $stocksByBranch = [];

        if ($branchId) {
            $stocks = BranchFridgeStock::query()
                ->where('branch_id', $branchId)
                ->where('quantity', '>', 0)
                ->with('product:id,name')
                ->orderBy('product_id')
                ->get()
                ->map($mapStockRow)
                ->values()
                ->all();
        } else {
            $branches = Branch::query()->orderBy('name')->get(['id', 'name']);
            $grouped = BranchFridgeStock::query()
                ->where('quantity', '>', 0)
                ->with('product:id,name')
                ->get()
                ->groupBy('branch_id');

            $stocksByBranch = $branches->map(function (Branch $branch) use ($grouped, $mapStockRow) {
                $items = ($grouped[$branch->id] ?? collect())
                    ->sortBy(fn (BranchFridgeStock $s) => $s->product?->name ?? '')
                    ->map($mapStockRow)
                    ->values()
                    ->all();

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'items' => $items,
                    'total_units' => round(collect($items)->sum('quantity'), 2),
                ];
            })->values()->all();
        }

        return [
            'configs' => $configs->values()->all(),
            'categories' => $categories->values()->all(),
            'finishedProducts' => $finishedProducts->values()->all(),
            'stocks' => $stocks,
            'stocksByBranch' => $stocksByBranch,
        ];
    }

    public function storeConfig(Request $request): RedirectResponse
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'size' => 'nullable|string|max:64',
            'deduct_on_sale' => 'required|in:none,all,custom',
            'ingredient_rules' => 'nullable|array',
            'ingredient_rules.*.raw_material_id' => 'required|integer',
            'ingredient_rules.*.deduct_on_sale' => 'boolean',
        ]);

        $product = Product::query()->where('type', 'finished')->findOrFail($data['product_id']);
        $size = (string) ($data['size'] ?? '');

        $exists = FridgeProductConfig::query()
            ->where('product_id', $product->id)
            ->where('size', $size)
            ->exists();
        if ($exists) {
            return back()->withErrors(['product_id' => 'هذا المنتج والمقاس مُعرَّفان مسبقاً للتلاجة.']);
        }

        $config = FridgeProductConfig::create([
            'product_id' => $product->id,
            'size' => $size,
            'deduct_on_pull' => FridgeProductConfig::MODE_NONE,
            'deduct_on_sale' => $data['deduct_on_sale'],
            'is_active' => true,
        ]);

        if ($data['deduct_on_sale'] === 'custom') {
            $this->fridgeService->syncIngredientRules($config, $this->saleRulesFromRequest($data['ingredient_rules'] ?? []));
        }

        return back()->with('success', 'تمت إضافة منتج التلاجة.');
    }

    public function updateConfig(Request $request, FridgeProductConfig $config): RedirectResponse
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $data = $request->validate([
            'deduct_on_sale' => 'required|in:none,all,custom',
            'ingredient_rules' => 'nullable|array',
            'ingredient_rules.*.raw_material_id' => 'required|integer',
            'ingredient_rules.*.deduct_on_sale' => 'boolean',
        ]);

        $config->update([
            'deduct_on_pull' => FridgeProductConfig::MODE_NONE,
            'deduct_on_sale' => $data['deduct_on_sale'],
        ]);

        $config->ingredientRules()->delete();
        if ($data['deduct_on_sale'] === 'custom') {
            $this->fridgeService->syncIngredientRules($config, $this->saleRulesFromRequest($data['ingredient_rules'] ?? []));
        }

        return back()->with('success', 'تم تحديث إعدادات منتج التلاجة.');
    }

    public function destroyConfig(FridgeProductConfig $config): RedirectResponse
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $hasPending = FridgePendingLabel::query()
            ->where('fridge_product_config_id', $config->id)
            ->where('status', FridgePendingLabel::STATUS_PENDING)
            ->exists();
        if ($hasPending) {
            return back()->withErrors(['fridge' => 'لا يمكن الحذف: توجد ملصقات بانتظار السحب.']);
        }

        $config->delete();

        return back()->with('success', 'تم حذف منتج التلاجة من الإعدادات.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{deduct_on_pull: bool, deduct_on_sale: bool}>
     */
    private function saleRulesFromRequest(array $rows): array
    {
        $rules = [];
        foreach ($rows as $rule) {
            if (! ($rule['deduct_on_sale'] ?? false)) {
                continue;
            }
            $rules[(int) $rule['raw_material_id']] = [
                'deduct_on_pull' => false,
                'deduct_on_sale' => true,
            ];
        }

        return $rules;
    }

    public function productIngredients(Request $request): JsonResponse
    {
        $this->requireHubRoles();

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'size' => 'nullable|string|max:64',
        ]);

        $rows = DB::table('ingredients')
            ->join('products', 'products.id', '=', 'ingredients.raw_material_id')
            ->where('ingredients.finished_product_id', $data['product_id'])
            ->where('ingredients.size', $data['size'] ?? '')
            ->select('ingredients.raw_material_id', 'ingredients.quantity_consumed', 'products.name', 'products.consume_unit')
            ->get();

        return response()->json(['ingredients' => $rows]);
    }

    /** @return array<int, float> */
    public static function pendingUnitsByConfigId(): array
    {
        $totals = [];

        foreach (DB::table('fridge_pending_label_items')
            ->join('fridge_pending_labels', 'fridge_pending_labels.id', '=', 'fridge_pending_label_items.fridge_pending_label_id')
            ->where('fridge_pending_labels.status', FridgePendingLabel::STATUS_PENDING)
            ->selectRaw('fridge_pending_label_items.fridge_product_config_id as cid, SUM(fridge_pending_label_items.unit_count) as total')
            ->groupBy('fridge_pending_label_items.fridge_product_config_id')
            ->get() as $row) {
            $totals[(int) $row->cid] = (float) $row->total;
        }

        foreach (FridgePendingLabel::query()
            ->where('status', FridgePendingLabel::STATUS_PENDING)
            ->whereNotNull('fridge_product_config_id')
            ->whereDoesntHave('items')
            ->get() as $label) {
            $id = (int) $label->fridge_product_config_id;
            $totals[$id] = ($totals[$id] ?? 0) + (float) $label->unit_count;
        }

        return $totals;
    }

    public function storeCombinedLabel(Request $request): JsonResponse
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $data = $request->validate([
            'items' => 'required|array|min:2',
            'items.*.fridge_product_config_id' => 'required|exists:fridge_product_configs,id',
            'items.*.unit_count' => 'required|numeric|min:0.001',
        ]);

        $configs = FridgeProductConfig::query()
            ->whereIn('id', collect($data['items'])->pluck('fridge_product_config_id'))
            ->get()
            ->keyBy('id');

        $label = DB::transaction(function () use ($data, $configs) {
            $label = FridgePendingLabel::create([
                'label_code' => $this->generateFridgeLabelCode(),
                'status' => FridgePendingLabel::STATUS_PENDING,
            ]);

            foreach ($data['items'] as $row) {
                $config = $configs->get((int) $row['fridge_product_config_id']);
                if (! $config) {
                    continue;
                }
                FridgePendingLabelItem::create([
                    'fridge_pending_label_id' => $label->id,
                    'fridge_product_config_id' => $config->id,
                    'product_id' => $config->product_id,
                    'size' => $config->size ?? '',
                    'unit_count' => (float) $row['unit_count'],
                ]);
            }

            return $label;
        });

        $label->load(['items.product']);

        return response()->json([
            'id' => $label->id,
            'label_code' => $label->label_code,
            'items' => $label->items->map(fn (FridgePendingLabelItem $item) => [
                'product_name' => $item->product?->name,
                'size' => $item->size,
                'unit_count' => (float) $item->unit_count,
            ])->values()->all(),
        ]);
    }

    public function storeLabel(Request $request, FridgeProductConfig $config): JsonResponse|RedirectResponse
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $data = $request->validate([
            'unit_count' => 'required|numeric|min:0.001',
        ]);

        $units = (float) $data['unit_count'];

        $label = FridgePendingLabel::create([
            'fridge_product_config_id' => $config->id,
            'product_id' => $config->product_id,
            'size' => $config->size ?? '',
            'label_code' => $this->generateFridgeLabelCode(),
            'unit_count' => $units,
            'status' => FridgePendingLabel::STATUS_PENDING,
        ]);

        FridgePendingLabelItem::create([
            'fridge_pending_label_id' => $label->id,
            'fridge_product_config_id' => $config->id,
            'product_id' => $config->product_id,
            'size' => $config->size ?? '',
            'unit_count' => $units,
        ]);

        $label->loadMissing('product');

        if ($request->expectsJson() || $request->isXmlHttpRequest()) {
            return response()->json([
                'id' => $label->id,
                'label_code' => $label->label_code,
                'unit_count' => (float) $label->unit_count,
                'product_name' => $label->product?->name,
                'size' => $label->size,
            ]);
        }

        return redirect()->route('admin.fridge.labels.print', $label);
    }

    public function printLabel(FridgePendingLabel $label)
    {
        $this->requireHubRoles();
        if (! $this->isCentralHub()) {
            abort(403);
        }

        $lines = $label->resolveLines();

        return Inertia::render('Admin/RawMaterials/PrintFridgeLabel', [
            'label' => [
                'label_code' => $label->label_code,
                'status' => $label->status,
                'unit_count' => $lines->count() === 1 ? (float) $lines->first()->unit_count : null,
                'size' => $lines->count() === 1 ? $lines->first()->size : null,
            ],
            'productName' => $lines->count() === 1
                ? ($lines->first()->product?->name ?? '')
                : 'كود مجمّع — '.$lines->count().' منتجات',
            'lines' => $lines->map(fn (FridgePendingLabelItem $item) => [
                'product_name' => $item->product?->name ?? '—',
                'size' => $item->size,
                'unit_count' => (float) $item->unit_count,
            ])->values()->all(),
        ]);
    }

    public function fridgePullForm()
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['cashier', 'admin', 'super admin'])) {
            abort(403);
        }

        if ($this->isCentralHub()) {
            return redirect()->route('dashboard')
                ->with('error', 'اختر فرعاً من لوحة التحكم لسحب منتجات التلاجة.');
        }

        $branchId = BranchContext::requireId();
        [$dayStart, $dayEnd] = Employee::businessDayBoundsForAnchor(Employee::businessDayAnchorFromNow());

        $todayPulls = FridgePendingLabel::query()
            ->with(['product:id,name', 'items.product:id,name'])
            ->where('branch_id', $branchId)
            ->where('status', FridgePendingLabel::STATUS_RECEIVED)
            ->whereBetween('received_at', [$dayStart, $dayEnd])
            ->orderByDesc('received_at')
            ->get()
            ->map(function (FridgePendingLabel $l) {
                $lines = $l->resolveLines();
                $combined = $lines->count() > 1;

                return [
                    'id' => $l->id,
                    'received_at' => $l->received_at?->format('H:i'),
                    'product_name' => $combined
                        ? 'كود مجمّع ('.$lines->count().' منتجات)'
                        : ($lines->first()?->product?->name ?? '—'),
                    'unit_count' => $combined ? null : (float) ($lines->first()?->unit_count ?? 0),
                    'size' => $combined ? null : $lines->first()?->size,
                    'label_code' => $l->label_code,
                    'lines' => $combined ? $lines->map(fn ($item) => [
                        'product_name' => $item->product?->name,
                        'unit_count' => (float) $item->unit_count,
                        'size' => $item->size,
                    ])->values()->all() : [],
                ];
            });

        return Inertia::render('Admin/RawMaterials/FridgePull', [
            'todayPulls' => $todayPulls,
            'businessDayLabel' => Employee::periodTextForAnchorDate(Employee::businessDayAnchorFromNow()),
            'branchName' => Branch::find($branchId)?->name ?? '',
        ]);
    }

    public function fridgePullStore(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['cashier', 'admin', 'super admin'])) {
            abort(403);
        }

        $branchId = BranchContext::requireId();
        $data = $request->validate(['label_code' => 'required|string|max:64']);
        $code = strtoupper(trim($data['label_code']));

        $label = FridgePendingLabel::query()
            ->where('label_code', $code)
            ->where('status', FridgePendingLabel::STATUS_PENDING)
            ->first();

        if (! $label) {
            return back()->withErrors(['label_code' => 'كود التلاجة غير صالح أو تم سحبه مسبقاً.'])->withInput();
        }

        $lines = $label->resolveLines();

        if ($lines->isEmpty()) {
            return back()->withErrors(['label_code' => 'الملصق فارغ.'])->withInput();
        }

        $count = 0;

        DB::transaction(function () use ($label, $lines, $branchId, &$count) {
            foreach ($lines as $line) {
                $config = $line->config ?? FridgeProductConfig::find($line->fridge_product_config_id);
                if (! $config) {
                    continue;
                }
                BranchFridgeStock::adjust(
                    $branchId,
                    $config->product_id,
                    $config->size ?? '',
                    (float) $line->unit_count,
                    $label->tenant_id
                );
                $count++;
            }

            $label->update([
                'status' => FridgePendingLabel::STATUS_RECEIVED,
                'branch_id' => $branchId,
                'received_at' => now(),
            ]);
        });

        $message = $count > 1
            ? "تم سحب {$count} منتجات إلى التلاجة."
            : 'تم سحب المنتج إلى تلاجة الفرع.';

        return redirect()->route('admin.fridge.pull')->with('success', $message);
    }
}
