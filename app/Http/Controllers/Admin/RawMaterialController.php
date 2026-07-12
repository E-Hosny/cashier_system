<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\FridgeController;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchRawMaterialStock;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Product;
use App\Models\RawMaterialPendingLabel;
use App\Models\RawMaterialPendingLabelItem;
use App\Models\FridgePendingLabel;
use App\Models\StockMovement;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RawMaterialController extends Controller
{
    private function generateRawLabelCode(): string
    {
        // Keep barcode short for reliable scanner reading on small stickers.
        for ($i = 0; $i < 10; $i++) {
            $code = 'RM'.strtoupper(Str::random(8));
            $exists = RawMaterialPendingLabel::query()->where('label_code', $code)->exists();
            if (! $exists) {
                return $code;
            }
        }

        return 'RM'.strtoupper(Str::random(12));
    }

    private function userHasAnyRole(array $roles): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    private function requireAnyRole(array $roles): void
    {
        if (! $this->userHasAnyRole($roles)) {
            abort(403, 'غير مصرح لك بالوصول لهذه الصفحة');
        }
    }

    private function isCentralHub(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('super admin') && ! BranchContext::hasBranch();
    }

    private function currentBusinessDayBounds(): array
    {
        return Employee::businessDayBoundsForAnchor(Employee::businessDayAnchorFromNow());
    }

    public function index(Request $request)
    {
        $this->requireAnyRole(['admin', 'super admin', 'cashier']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.branch-pull');
        }

        $hubBranches = Branch::query()->orderBy('name')->get(['id', 'name']);
        $viewScope = $request->get('view_scope', 'central');

        if ($viewScope !== 'central' && ! $hubBranches->contains('id', (int) $viewScope)) {
            $viewScope = 'central';
        }

        $categoryId = $request->get('category_id', '') !== '' ? (string) $request->get('category_id') : '';

        $rawMaterials = [];
        $branchDetail = null;

        if ($viewScope === 'central') {
            $query = Product::where('type', 'raw')->with('category')->latest();
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->integer('category_id'));
            }

            $rawMaterials = $query->get();
            $pendingSums = $this->pendingPieceTotalsByProduct();
            $rawMaterials = $rawMaterials->map(function (Product $m) use ($pendingSums) {
                $m->pending_pieces = (float) ($pendingSums[$m->id] ?? 0);

                return $m;
            });
        } else {
            $branchDetail = $this->buildBranchDetail((int) $viewScope, $request);
        }

        $fridgeBranchId = $viewScope !== 'central' ? (int) $viewScope : null;

        return Inertia::render('Admin/RawMaterials/Index', [
            'rawMaterials' => $rawMaterials,
            'pendingLabelsForBundle' => $viewScope === 'central' ? $this->pendingLabelsForBundlePayload() : [],
            'rawMaterialCategories' => Category::forRawMaterials()->orderBy('name')->get(['id', 'name']),
            'hubBranches' => $hubBranches,
            'branchDetail' => $branchDetail,
            'fridge' => FridgeController::buildIndexPayload($fridgeBranchId),
            'filters' => [
                'category_id' => $categoryId,
                'view_scope' => $viewScope,
                'tab' => $request->get('tab', 'materials'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBranchDetail(int $branchId, Request $request): array
    {
        [$dayStart, $dayEnd] = $this->currentBusinessDayBounds();

        $branch = Branch::query()->findOrFail($branchId);

        $query = Product::where('type', 'raw')->with('category')->orderBy('name');
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $stockRows = BranchRawMaterialStock::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('product_id');

        $materials = $query->get()->map(function (Product $product) use ($stockRows) {
            $row = $stockRows[$product->id] ?? null;
            $stockConsume = (float) ($row?->stock ?? 0);
            $qpu = (float) ($product->quantity_per_unit ?: 0);
            $pieces = $qpu > 0 ? $stockConsume / $qpu : $stockConsume;
            $alertConsume = $row?->stock_alert_threshold ?? $product->stock_alert_threshold;
            $alertPieces = $alertConsume && $qpu > 0 ? ((float) $alertConsume) / $qpu : $alertConsume;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'consume_unit' => $product->consume_unit,
                'quantity_per_unit' => $product->quantity_per_unit,
                'purchase_price' => $product->purchase_price,
                'unit_consume_price' => $product->unit_consume_price,
                'purchase_unit' => $product->purchase_unit,
                'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
                'branch_stock_pieces' => round($pieces, 2),
                'branch_stock_consume' => round($stockConsume, 2),
                'alert_pieces' => $alertPieces !== null ? round((float) $alertPieces, 2) : null,
                'is_low' => $alertPieces !== null && $pieces <= (float) $alertPieces,
            ];
        })->values();

        $todayPulls = RawMaterialPendingLabel::query()
            ->with(['product:id,name,unit', 'items.product:id,name,unit'])
            ->where('branch_id', $branchId)
            ->where('status', RawMaterialPendingLabel::STATUS_RECEIVED)
            ->whereBetween('received_at', [$dayStart, $dayEnd])
            ->orderByDesc('received_at')
            ->get()
            ->map(fn (RawMaterialPendingLabel $label) => $this->formatPullRow($label));

        return [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'materials' => $materials,
            'todayPulls' => $todayPulls->values()->all(),
            'businessDayLabel' => Employee::periodTextForAnchorDate(Employee::businessDayAnchorFromNow()),
            'can_edit_stock' => auth()->user()?->hasRole('super admin') ?? false,
        ];
    }

    public function updateBranchStock(Request $request, Branch $branch, Product $raw_material): RedirectResponse
    {
        if (! auth()->user()?->hasRole('super admin')) {
            abort(403);
        }

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.index')
                ->with('error', 'تعديل مخزون الفرع من العرض المركزي فقط.');
        }

        if ($raw_material->type !== 'raw') {
            abort(404);
        }

        $data = $request->validate([
            'stock_pieces' => 'nullable|numeric|min:0',
            'stock_consume' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ]);

        if (! isset($data['stock_pieces']) && ! isset($data['stock_consume'])) {
            return redirect()->back()->with('error', 'أدخل الكمية بالقطع أو بالوحدة الاستهلاكية.');
        }

        $perUnit = (float) ($raw_material->quantity_per_unit ?: 1);
        if (isset($data['stock_consume'])) {
            $newConsume = (float) $data['stock_consume'];
        } else {
            $newConsume = (float) $data['stock_pieces'] * $perUnit;
        }

        $row = BranchRawMaterialStock::query()
            ->where('branch_id', $branch->id)
            ->where('product_id', $raw_material->id)
            ->first();

        $oldConsume = (float) ($row?->stock ?? 0);
        $delta = $newConsume - $oldConsume;

        BranchRawMaterialStock::query()->updateOrCreate(
            ['branch_id' => $branch->id, 'product_id' => $raw_material->id],
            [
                'tenant_id' => $raw_material->tenant_id ?? auth()->user()?->tenant_id,
                'stock' => $newConsume,
            ]
        );

        if (abs($delta) > 0.0001) {
            StockMovement::create([
                'product_id' => $raw_material->id,
                'branch_id' => $branch->id,
                'quantity' => $delta,
                'type' => 'branch_manual_adjustment',
                'tenant_id' => $raw_material->tenant_id,
            ]);
        }

        $params = [
            'view_scope' => (string) $branch->id,
            'tab' => 'materials',
        ];
        if ($request->filled('category_id')) {
            $params['category_id'] = $request->get('category_id');
        }

        return redirect()->route('admin.raw-materials.index', $params)
            ->with('success', 'تم تحديث مخزون الفرع لـ «'.$raw_material->name.'».');
    }

    public function allBranchesPullsReport(Request $request)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.branch-pull');
        }

        $maxBusinessDay = Employee::businessDayAnchorFromNow();
        $selectedDate = $request->input('date', $maxBusinessDay);
        $branchFilter = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = $maxBusinessDay;
        }

        if ($selectedDate > $maxBusinessDay) {
            $selectedDate = $maxBusinessDay;
        }

        [$dayStart, $dayEnd] = Employee::businessDayBoundsForAnchor($selectedDate);

        $rawQuery = RawMaterialPendingLabel::query()
            ->with(['product:id,name,unit', 'items.product:id,name,unit', 'branch:id,name'])
            ->where('status', RawMaterialPendingLabel::STATUS_RECEIVED)
            ->whereNotNull('branch_id')
            ->whereBetween('received_at', [$dayStart, $dayEnd]);

        $fridgeQuery = FridgePendingLabel::query()
            ->with(['product:id,name', 'items.product:id,name', 'branch:id,name'])
            ->where('status', FridgePendingLabel::STATUS_RECEIVED)
            ->whereNotNull('branch_id')
            ->whereBetween('received_at', [$dayStart, $dayEnd]);

        if ($branchFilter) {
            $rawQuery->where('branch_id', $branchFilter);
            $fridgeQuery->where('branch_id', $branchFilter);
        }

        $rawPulls = $rawQuery->get()->map(function (RawMaterialPendingLabel $label) {
            $row = $this->formatPullRow($label);
            $row['row_key'] = 'raw-'.$label->id;
            $row['type'] = 'raw';
            $row['type_label'] = 'مواد خام';
            $row['branch_id'] = $label->branch_id;
            $row['branch_name'] = $label->branch?->name ?? '—';

            return $row;
        });

        $fridgePulls = $fridgeQuery->get()->map(function (FridgePendingLabel $label) {
            $row = $this->formatFridgePullRow($label);
            $row['row_key'] = 'fridge-'.$label->id;
            $row['type'] = 'fridge';
            $row['type_label'] = 'تلاجة';
            $row['branch_id'] = $label->branch_id;
            $row['branch_name'] = $label->branch?->name ?? '—';

            return $row;
        });

        $pulls = $rawPulls
            ->concat($fridgePulls)
            ->sortByDesc(fn (array $row) => $row['received_at'] ?? '')
            ->values();

        $summaryByBranch = $pulls
            ->groupBy('branch_name')
            ->map(fn ($items, $branchName) => [
                'branch_name' => $branchName,
                'pull_count' => $items->count(),
                'raw_count' => $items->where('type', 'raw')->count(),
                'fridge_count' => $items->where('type', 'fridge')->count(),
            ])
            ->values()
            ->sortBy('branch_name')
            ->values()
            ->all();

        $hubBranches = Branch::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/RawMaterials/BranchPullsReport', [
            'pulls' => $pulls->all(),
            'selectedDate' => $selectedDate,
            'maxBusinessDay' => $maxBusinessDay,
            'businessDayLabel' => Employee::periodTextForAnchorDate($selectedDate),
            'summaryByBranch' => $summaryByBranch,
            'totalPulls' => $pulls->count(),
            'totalRawPulls' => $rawPulls->count(),
            'totalFridgePulls' => $fridgePulls->count(),
            'hubBranches' => $hubBranches,
            'filters' => [
                'branch_id' => $branchFilter ? (string) $branchFilter : '',
            ],
        ]);
    }

    public function branchPullForm(Request $request)
    {
        $this->requireAnyRole(['cashier', 'admin', 'super admin']);

        if ($this->isCentralHub()) {
            return redirect()->route('dashboard')
                ->with('error', 'اختر فرعاً من لوحة التحكم لسحب المواد الخام.');
        }

        $branchId = BranchContext::requireId();
        $maxBusinessDay = Employee::businessDayAnchorFromNow();
        $selectedDate = $request->input('date', $maxBusinessDay);

        try {
            $selectedDate = Carbon::parse($selectedDate)->toDateString();
        } catch (\Throwable) {
            $selectedDate = $maxBusinessDay;
        }

        if ($selectedDate > $maxBusinessDay) {
            $selectedDate = $maxBusinessDay;
        }

        [$dayStart, $dayEnd] = Employee::businessDayBoundsForAnchor($selectedDate);

        $todayPulls = RawMaterialPendingLabel::query()
            ->with(['product:id,name,unit', 'items.product:id,name,unit'])
            ->where('branch_id', $branchId)
            ->where('status', RawMaterialPendingLabel::STATUS_RECEIVED)
            ->whereBetween('received_at', [$dayStart, $dayEnd])
            ->orderByDesc('received_at')
            ->get()
            ->map(fn (RawMaterialPendingLabel $label) => $this->formatBranchPullRow($label));

        return Inertia::render('Admin/RawMaterials/BranchPull', [
            'todayPulls' => $todayPulls,
            'selectedDate' => $selectedDate,
            'maxBusinessDay' => $maxBusinessDay,
            'businessDayLabel' => Employee::periodTextForAnchorDate($selectedDate),
            'branchName' => Branch::find($branchId)?->name ?? '',
        ]);
    }

    public function branchPullStore(Request $request): RedirectResponse
    {
        $this->requireAnyRole(['cashier', 'admin', 'super admin']);

        $branchId = BranchContext::requireId();

        $data = $request->validate([
            'label_code' => 'required|string|max:64',
        ]);

        $code = strtoupper(trim($data['label_code']));

        $label = RawMaterialPendingLabel::query()
            ->where('label_code', $code)
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->first();

        if (! $label) {
            return back()->withErrors(['label_code' => 'الكود غير صالح أو تم سحبه مسبقاً.'])->withInput();
        }

        $lines = $label->resolveLines();

        if ($lines->isEmpty()) {
            return back()->withErrors(['label_code' => 'الملصق فارغ.'])->withInput();
        }

        $warnings = [];
        $lineCount = 0;

        DB::transaction(function () use ($label, $lines, $branchId, &$warnings, &$lineCount) {
            foreach ($lines as $line) {
                $product = $line->product;
                if (! $product || $product->type !== 'raw') {
                    continue;
                }

                $amount = (float) $line->consume_amount;
                $centralBefore = (float) $product->stock;
                if ($centralBefore < $amount) {
                    $warnings[] = $product->name;
                }

                $product->decrement('stock', $amount);

                BranchRawMaterialStock::adjust($branchId, $product->id, $amount, $product->tenant_id);

                StockMovement::create([
                    'product_id' => $product->id,
                    'branch_id' => $branchId,
                    'quantity' => $amount,
                    'type' => 'branch_pull',
                    'tenant_id' => $product->tenant_id,
                ]);

                StockMovement::create([
                    'product_id' => $product->id,
                    'quantity' => -$amount,
                    'type' => 'branch_pull_central',
                    'tenant_id' => $product->tenant_id,
                ]);

                $lineCount++;
            }

            $label->update([
                'status' => RawMaterialPendingLabel::STATUS_RECEIVED,
                'branch_id' => $branchId,
                'received_at' => now(),
            ]);
        });

        if ($lineCount === 0) {
            return back()->withErrors(['label_code' => 'المادة المرتبطة بهذا الكود غير صالحة.'])->withInput();
        }

        $message = $lineCount > 1
            ? "تم سحب {$lineCount} مواد وإضافتها لمخزون الفرع."
            : 'تم سحب الكمية وإضافتها لمخزون الفرع.';
        if ($warnings !== []) {
            $message .= ' تنبيه: المخزون المركزي كان ناقصاً لـ: '.implode('، ', array_unique($warnings)).'.';
        }

        return redirect()->route('admin.raw-materials.branch-pull')
            ->with('success', $message);
    }

    public function storeCombinedLabel(Request $request): JsonResponse
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            abort(403);
        }

        $data = $request->validate([
            'label_ids' => 'required|array|min:2',
            'label_ids.*' => 'required|integer|distinct|exists:raw_material_pending_labels,id',
        ]);

        $sourceLabels = RawMaterialPendingLabel::query()
            ->with('product:id,name,unit,consume_unit,type')
            ->whereIn('id', $data['label_ids'])
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->whereNotNull('product_id')
            ->whereDoesntHave('items')
            ->lockForUpdate()
            ->get();

        if ($sourceLabels->count() !== count($data['label_ids'])) {
            return response()->json([
                'message' => 'يجب اختيار أكواد معلّقة فقط (لم تُسحَب ولم تُجمَّع مسبقاً).',
            ], 422);
        }

        $bundle = DB::transaction(function () use ($sourceLabels) {
            $bundle = RawMaterialPendingLabel::create([
                'label_code' => $this->generateRawLabelCode(),
                'status' => RawMaterialPendingLabel::STATUS_PENDING,
            ]);

            foreach ($sourceLabels as $source) {
                RawMaterialPendingLabelItem::create([
                    'raw_material_pending_label_id' => $bundle->id,
                    'product_id' => $source->product_id,
                    'piece_count' => $source->piece_count,
                    'consume_amount' => $source->consume_amount,
                    'source_label_id' => $source->id,
                ]);

                $source->update([
                    'status' => RawMaterialPendingLabel::STATUS_BUNDLED,
                ]);
            }

            return $bundle->load(['items.product', 'items.sourceLabel:id,label_code']);
        });

        return response()->json([
            'id' => $bundle->id,
            'label_code' => $bundle->label_code,
            'items' => $bundle->items->map(fn (RawMaterialPendingLabelItem $item) => [
                'product_name' => $item->product?->name,
                'piece_count' => (float) $item->piece_count,
                'unit' => $item->product?->unit,
                'consume_amount' => (float) $item->consume_amount,
                'consume_unit' => $item->product?->consume_unit,
                'source_label_code' => $item->sourceLabel?->label_code,
            ])->values()->all(),
        ]);
    }

    /** @return array<int, float> */
    private function pendingPieceTotalsByProduct(): array
    {
        $totals = [];

        RawMaterialPendingLabel::query()
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->with(['items', 'product'])
            ->get()
            ->each(function (RawMaterialPendingLabel $label) use (&$totals) {
                foreach ($label->resolveLines() as $line) {
                    $productId = (int) $line->product_id;
                    $totals[$productId] = ($totals[$productId] ?? 0) + (float) $line->piece_count;
                }
            });

        return $totals;
    }

    /** @return array<int, array<string, mixed>> */
    private function pendingLabelsForBundlePayload(): array
    {
        return RawMaterialPendingLabel::query()
            ->with('product:id,name,unit,consume_unit')
            ->where('status', RawMaterialPendingLabel::STATUS_PENDING)
            ->whereNotNull('product_id')
            ->whereDoesntHave('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (RawMaterialPendingLabel $label) => [
                'id' => $label->id,
                'label_code' => $label->label_code,
                'product_id' => $label->product_id,
                'product_name' => $label->product?->name ?? '—',
                'piece_count' => (float) $label->piece_count,
                'consume_amount' => (float) $label->consume_amount,
                'unit' => $label->product?->unit ?? '',
                'consume_unit' => $label->product?->consume_unit ?? '',
                'created_at' => $label->created_at?->format('Y-m-d H:i'),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function formatPullRow(RawMaterialPendingLabel $label): array
    {
        $lines = $label->resolveLines();
        $combined = $lines->count() > 1;

        return [
            'id' => $label->id,
            'received_at' => $label->received_at?->format('Y-m-d H:i'),
            'product_name' => $combined
                ? 'كود مجمّع ('.$lines->count().' مواد)'
                : ($lines->first()?->product?->name ?? '—'),
            'piece_count' => $combined ? null : (float) ($lines->first()?->piece_count ?? 0),
            'unit' => $combined ? '' : ($lines->first()?->product?->unit ?? ''),
            'label_code' => $label->label_code,
            'lines' => $combined ? $lines->map(fn ($line) => [
                'product_name' => $line->product?->name,
                'piece_count' => (float) $line->piece_count,
                'unit' => $line->product?->unit ?? '',
            ])->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function formatFridgePullRow(FridgePendingLabel $label): array
    {
        $lines = $label->resolveLines();
        $combined = $lines->count() > 1;

        return [
            'id' => $label->id,
            'received_at' => $label->received_at?->format('Y-m-d H:i'),
            'product_name' => $combined
                ? 'كود مجمّع ('.$lines->count().' منتجات)'
                : ($lines->first()?->product?->name ?? '—'),
            'piece_count' => $combined ? null : (float) ($lines->first()?->unit_count ?? 0),
            'unit' => $combined ? '' : 'وحدة',
            'label_code' => $label->label_code,
            'lines' => $combined ? $lines->map(fn ($item) => [
                'product_name' => $item->product?->name,
                'piece_count' => (float) $item->unit_count,
                'unit' => 'وحدة',
                'size' => $item->size ?? '',
            ])->values()->all() : [],
        ];
    }

    /** @return array<string, mixed> */
    private function formatBranchPullRow(RawMaterialPendingLabel $label): array
    {
        $row = $this->formatPullRow($label);
        $row['received_at'] = $label->received_at?->format('H:i');

        return $row;
    }

    public function receiveByBarcodeForm(): RedirectResponse
    {
        return redirect()->route('admin.raw-materials.branch-pull');
    }

    public function receiveByBarcode(Request $request): RedirectResponse
    {
        return $this->branchPullStore($request);
    }

    public function create()
    {
        $this->requireAnyRole(['super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('dashboard')->with('error', 'إدارة المواد الخام من العرض المركزي فقط.');
        }

        return Inertia::render('Admin/RawMaterials/Create', [
            'rawMaterialCategories' => Category::forRawMaterials()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAnyRole(['super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('dashboard')->with('error', 'إدارة المواد الخام من العرض المركزي فقط.');
        }

        $request->merge([
            'category_id' => $request->filled('category_id') ? (int) $request->category_id : null,
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'price_per_piece' => 'nullable|numeric|min:0',
            'consume_unit' => 'required|string|in:مللي,جرام,قطعة,كوب',
            'quantity_per_unit' => 'required|numeric|min:0.001',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
            'unit_consume_price' => 'nullable|numeric|min:0',
            'category_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! Category::forRawMaterials()->whereKey((int) $value)->exists()) {
                        $fail('فئة المواد الخام غير صالحة.');
                    }
                },
            ],
        ]);

        $data['type'] = 'raw';
        $data['purchase_unit'] = $data['unit'];
        $data['purchase_quantity'] = 1;
        $data['purchase_price'] = $data['price_per_piece'];
        unset($data['price_per_piece']);
        $product = Product::create($data);
        if (empty($product->barcode)) {
            $product->forceFill(['barcode' => 'RM-'.strtoupper(Str::ulid())])->save();
        }

        return redirect()->route('admin.raw-materials.index')->with('success', 'تمت إضافة المادة الخام بنجاح.');
    }

    public function show(Product $product)
    {
        //
    }

    public function edit(Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.branch-pull');
        }

        return Inertia::render('Admin/RawMaterials/Edit', [
            'rawMaterial' => $raw_material,
            'rawMaterialCategories' => Category::forRawMaterials()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.branch-pull');
        }

        $request->merge([
            'category_id' => $request->filled('category_id') ? (int) $request->category_id : null,
        ]);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'price_per_piece' => 'nullable|numeric|min:0',
            'consume_unit' => 'required|string|in:مللي,جرام,قطعة,كوب',
            'quantity_per_unit' => 'required|numeric|min:0.001',
            'stock' => 'required|numeric|min:0',
            'stock_alert_threshold' => 'nullable|numeric|min:0',
            'unit_consume_price' => 'nullable|numeric|min:0',
            'category_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! Category::forRawMaterials()->whereKey((int) $value)->exists()) {
                        $fail('فئة المواد الخام غير صالحة.');
                    }
                },
            ],
        ]);

        $data['purchase_unit'] = $data['unit'];
        $data['purchase_quantity'] = 1;
        $data['purchase_price'] = $data['price_per_piece'];
        unset($data['price_per_piece']);
        $raw_material->update($data);
        if ($raw_material->type === 'raw' && empty($raw_material->barcode)) {
            $raw_material->forceFill(['barcode' => 'RM-'.strtoupper(Str::ulid())])->save();
        }

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم تحديث المادة الخام بنجاح.');
    }

    public function storeLabel(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            abort(403);
        }

        if ($raw_material->type !== 'raw') {
            abort(404);
        }

        $data = $request->validate([
            'piece_count' => 'required|numeric|min:0.001',
        ]);

        $pieces = (float) $data['piece_count'];
        $perUnit = (float) ($raw_material->quantity_per_unit ?: 1);
        $consumeAmount = $pieces * $perUnit;

        $label = RawMaterialPendingLabel::create([
            'product_id' => $raw_material->id,
            'label_code' => $this->generateRawLabelCode(),
            'piece_count' => $pieces,
            'consume_amount' => $consumeAmount,
            'status' => RawMaterialPendingLabel::STATUS_PENDING,
        ]);

        if ($request->isXmlHttpRequest()) {
            $label->loadMissing('product');

            return response()->json([
                'id' => $label->id,
                'label_code' => $label->label_code,
                'piece_count' => (float) $label->piece_count,
                'consume_amount' => (float) $label->consume_amount,
                'status' => $label->status,
                'product_name' => $label->product?->name,
                'unit' => $label->product?->unit,
                'consume_unit' => $label->product?->consume_unit,
            ]);
        }

        return redirect()->route('admin.raw-materials.labels.print', $label);
    }

    public function printLabel(RawMaterialPendingLabel $label)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            abort(403);
        }

        $label->loadMissing(['product', 'items.product']);

        $lines = $label->resolveLines();
        $isCombined = $lines->count() > 1;

        return Inertia::render('Admin/RawMaterials/PrintLabel', [
            'label' => [
                'id' => $label->id,
                'label_code' => $label->label_code,
                'piece_count' => $label->piece_count !== null ? (float) $label->piece_count : null,
                'consume_amount' => $label->consume_amount !== null ? (float) $label->consume_amount : null,
                'status' => $label->status,
            ],
            'productName' => $isCombined
                ? 'كود مجمّع ('.$lines->count().' مواد)'
                : ($label->product?->name ?? $lines->first()?->product?->name ?? ''),
            'unit' => $isCombined ? '' : ($label->product?->unit ?? $lines->first()?->product?->unit ?? ''),
            'consumeUnit' => $isCombined ? '' : ($label->product?->consume_unit ?? $lines->first()?->product?->consume_unit ?? ''),
            'lines' => $isCombined ? $lines->map(fn (RawMaterialPendingLabelItem $item) => [
                'product_name' => $item->product?->name,
                'piece_count' => (float) $item->piece_count,
                'unit' => $item->product?->unit,
                'consume_unit' => $item->product?->consume_unit,
                'consume_amount' => (float) $item->consume_amount,
            ])->values()->all() : [],
        ]);
    }

    public function addQuantityForm(Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.index');
        }

        return Inertia::render('Admin/RawMaterials/AddQuantity', [
            'rawMaterial' => $raw_material,
        ]);
    }

    public function addQuantity(Request $request, Product $raw_material)
    {
        $this->requireAnyRole(['admin', 'super admin']);

        if (! $this->isCentralHub()) {
            return redirect()->route('admin.raw-materials.index');
        }

        $data = $request->validate([
            'quantity_units' => 'required|numeric|min:0.001',
            'note' => 'nullable|string|max:255',
        ]);

        $unitsToAdd = (float) $data['quantity_units'];
        $perUnit = $raw_material->quantity_per_unit ?: 1;
        $quantityToAdd = $unitsToAdd * $perUnit;

        $raw_material->increment('stock', $quantityToAdd);

        StockMovement::create([
            'product_id' => $raw_material->id,
            'quantity' => $quantityToAdd,
            'type' => 'manual_addition',
            'related_order_id' => null,
            'related_purchase_id' => null,
        ]);

        return redirect()->route('admin.raw-materials.index')->with('success', 'تمت إضافة الكمية للمخزون بنجاح.');
    }

    public function destroy(Product $raw_material)
    {
        $this->requireAnyRole(['super admin']);

        if (! $this->isCentralHub()) {
            abort(403);
        }

        $raw_material->delete();

        return redirect()->route('admin.raw-materials.index')->with('success', 'تم حذف المادة الخام بنجاح.');
    }
}
