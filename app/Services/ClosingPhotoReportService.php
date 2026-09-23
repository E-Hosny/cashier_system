<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchFridgeStock;
use App\Models\ClosingPhotoReportFridgeCount;
use App\Models\ClosingPhotoReportItem;
use App\Models\ClosingPhotoReportPhoto;
use App\Models\ClosingPhotoReportSubmission;
use App\Models\FridgeProductConfig;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ClosingPhotoReportService
{
    /**
     * هل يجب منع المدير من تقارير المبيعات الآن؟
     *
     * @return array{blocked: bool, reason?: string, requirement?: array}|null
     */
    public function gateForUser(User $user): ?array
    {
        if ($user->hasRole('super admin')) {
            return null;
        }

        if (! $user->hasRole('admin')) {
            return null;
        }

        $tenant = $this->tenantFor($user);
        if (! $tenant || ! $tenant->closing_photo_reports_enabled) {
            return null;
        }

        $branchId = BranchContext::id() ?: $user->branch_id;
        if (! $branchId) {
            return [
                'blocked' => true,
                'reason' => 'يجب اختيار/تحديد فرع قبل فتح تقارير المبيعات أثناء تفعيل تقارير صور التقفيلة.',
            ];
        }

        $requirement = $this->currentRequirement($tenant, (int) $branchId);
        if (! $requirement) {
            return null;
        }

        if ($requirement['is_complete']) {
            return null;
        }

        return [
            'blocked' => true,
            'reason' => $requirement['block_reason']
                ?? ('يجب إكمال '.$requirement['closing_type_label'].' ليوم '.$requirement['business_date'].' قبل فتح تقارير المبيعات.'),
            'requirement' => $requirement,
        ];
    }

    /**
     * إخفاء أرقام المبيعات في تقفيل الوردية طالما الخاصية مفعّلة ومتطلب التقفيلة الحالي غير مكتمل.
     * السوبر أدمن مستثنى.
     *
     * @return array{blocked: bool, reason?: string, requirement?: array}|null
     */
    public function shouldHideShiftSales(User $user): ?array
    {
        if ($user->hasRole('super admin')) {
            return null;
        }

        $tenant = $this->tenantFor($user);
        if (! $tenant || ! $tenant->closing_photo_reports_enabled) {
            return null;
        }

        $branchId = BranchContext::id() ?: $user->branch_id;
        if (! $branchId) {
            return [
                'blocked' => true,
                'reason' => 'يجب تحديد الفرع وإكمال التقفيلة قبل عرض المبيعات.',
            ];
        }

        $requirement = $this->currentRequirement($tenant, (int) $branchId);
        if (! $requirement || $requirement['is_complete']) {
            return null;
        }

        return [
            'blocked' => true,
            'reason' => $requirement['block_reason']
                ?? ('المبيعات مخفية حتى يتم إكمال '.$requirement['closing_type_label'].' ليوم '.$requirement['business_date'].'.'),
            'requirement' => $requirement,
        ];
    }

    /**
     * المتطلب الحالي حسب الوقت (إن وُجد).
     *
     * @return array<string, mixed>|null
     */
    public function currentRequirement(Tenant $tenant, int $branchId, ?Carbon $now = null): ?array
    {
        if (! $tenant->closing_photo_reports_enabled) {
            return null;
        }

        $now = $now ?: Carbon::now();
        $closingType = $this->resolveClosingType($tenant, $now);
        if (! $closingType) {
            return null;
        }

        $businessDate = $this->businessDateFor($now)->toDateString();
        $items = $this->activeItems($tenant->id, $closingType);

        if ($items->isEmpty()) {
            return null;
        }

        $submission = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branchId)
            ->where('closing_type', $closingType)
            ->whereDate('business_date', $businessDate)
            ->with(['photos', 'fridgeCounts'])
            ->first();

        $photosByItem = collect($submission?->photos ?? [])->keyBy('item_id');
        $requiredItems = $items->where('is_required', true);
        $uploadedRequired = $requiredItems->filter(fn ($item) => $photosByItem->has($item->id))->count();
        $photosComplete = $requiredItems->isNotEmpty() && $uploadedRequired === $requiredItems->count();

        $fridgeRequired = $this->activeFridgeConfigs((int) $tenant->id)->isNotEmpty();
        $fridgeComplete = ! $fridgeRequired || ($submission?->fridge_counted_at !== null);
        $isComplete = $photosComplete && $fridgeComplete;

        if ($submission) {
            $this->refreshCompletion($submission);
            if ($submission->isDirty()) {
                $submission->save();
            }
        }

        $branch = Branch::withoutGlobalScopes()->find($branchId);

        $blockReason = null;
        if (! $isComplete) {
            if (! $photosComplete) {
                $blockReason = 'يجب رفع صور '.$this->typeLabel($closingType).' ليوم '.$businessDate.' قبل فتح تقارير المبيعات.';
            } else {
                $blockReason = 'يجب إدخال أعداد التلاجة الفعلية ليوم '.$businessDate.' قبل فتح تقارير المبيعات.';
            }
        }

        return [
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'branch_name' => $branch?->name,
            'closing_type' => $closingType,
            'closing_type_label' => $this->typeLabel($closingType),
            'business_date' => $businessDate,
            'is_complete' => (bool) $isComplete,
            'photos_complete' => $photosComplete,
            'fridge_required' => $fridgeRequired,
            'fridge_complete' => $fridgeComplete,
            'required_count' => $requiredItems->count(),
            'uploaded_required_count' => $uploadedRequired,
            'items_count' => $items->count(),
            'submission_id' => $submission?->id,
            'block_reason' => $blockReason,
            'window' => $this->windowMeta($tenant, $closingType),
        ];
    }

    public function resolveClosingType(Tenant $tenant, ?Carbon $now = null): ?string
    {
        $now = $now ?: Carbon::now();
        $time = $now->format('H:i:s');

        $eveningStart = $this->normalizeTime($tenant->closing_evening_starts_at, '17:00:00');
        $eveningEnd = $this->normalizeTime($tenant->closing_evening_ends_at, '23:59:59');
        $dawnStart = $this->normalizeTime($tenant->closing_dawn_starts_at, '00:00:00');
        $dawnEnd = $this->normalizeTime($tenant->closing_dawn_ends_at, '06:59:59');

        if ($this->timeInRange($time, $eveningStart, $eveningEnd)) {
            return ClosingPhotoReportItem::TYPE_EVENING;
        }

        if ($this->timeInRange($time, $dawnStart, $dawnEnd)) {
            return ClosingPhotoReportItem::TYPE_DAWN;
        }

        return null;
    }

    /**
     * يوم تشغيلي بنفس منطق تقارير المبيعات (قبل 7 ص = اليوم السابق).
     */
    public function businessDateFor(?Carbon $now = null): Carbon
    {
        $now = $now ?: Carbon::now();

        if ($now->hour < 7) {
            return $now->copy()->subDay()->startOfDay();
        }

        return $now->copy()->startOfDay();
    }

    public function activeItems(int $tenantId, string $closingType)
    {
        return ClosingPhotoReportItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('closing_type', $closingType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * منتجات التلاجة النشطة (غير المؤرشفة).
     */
    public function activeFridgeConfigs(int $tenantId): Collection
    {
        return FridgeProductConfig::query()
            ->with('product:id,name,size_variants')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('exclude_from_closing_count', false)
            ->orderBy('id')
            ->get();
    }

    public function getOrCreateSubmission(Tenant $tenant, int $branchId, string $closingType, string $businessDate, ?int $userId = null): ClosingPhotoReportSubmission
    {
        return ClosingPhotoReportSubmission::withoutGlobalScopes()->firstOrCreate(
            [
                'branch_id' => $branchId,
                'closing_type' => $closingType,
                'business_date' => $businessDate,
            ],
            [
                'tenant_id' => $tenant->id,
                'submitted_by' => $userId,
            ]
        );
    }

    public function uploadPhoto(
        ClosingPhotoReportSubmission $submission,
        ClosingPhotoReportItem $item,
        UploadedFile $file,
        User $user
    ): ClosingPhotoReportPhoto {
        if ((int) $item->tenant_id !== (int) $submission->tenant_id) {
            throw ValidationException::withMessages(['file' => 'البند غير تابع لنفس الحساب.']);
        }

        if ($item->closing_type !== $submission->closing_type) {
            throw ValidationException::withMessages(['file' => 'البند لا يطابق نوع التقفيلة.']);
        }

        if (! $item->is_active) {
            throw ValidationException::withMessages(['file' => 'هذا البند غير مفعّل.']);
        }

        $disk = Storage::disk(ClosingPhotoReportPhoto::DISK);
        $dir = sprintf(
            'closing-reports/%d/%d/%s/%s',
            $submission->tenant_id,
            $submission->branch_id,
            $submission->business_date->format('Y-m-d'),
            $submission->closing_type
        );

        $path = $file->store($dir, [
            'disk' => ClosingPhotoReportPhoto::DISK,
            'visibility' => 'public',
        ]);

        if (! $path) {
            throw ValidationException::withMessages(['file' => 'فشل رفع الصورة إلى Spaces.']);
        }

        return DB::transaction(function () use ($submission, $item, $file, $user, $path, $disk) {
            $existing = ClosingPhotoReportPhoto::query()
                ->where('submission_id', $submission->id)
                ->where('item_id', $item->id)
                ->first();

            if ($existing?->path) {
                try {
                    $disk->delete($existing->path);
                } catch (\Throwable) {
                }
            }

            $photo = ClosingPhotoReportPhoto::query()->updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'item_id' => $item->id,
                ],
                [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]
            );

            if (! $submission->submitted_by) {
                $submission->submitted_by = $user->id;
            }

            $this->refreshCompletion($submission);
            $submission->save();

            return $photo;
        });
    }

    public function arePhotosComplete(ClosingPhotoReportSubmission $submission): bool
    {
        $items = $this->activeItems((int) $submission->tenant_id, $submission->closing_type);
        $required = $items->where('is_required', true);
        if ($required->isEmpty()) {
            return false;
        }

        $photos = ClosingPhotoReportPhoto::query()
            ->where('submission_id', $submission->id)
            ->pluck('item_id');

        return $required->every(fn ($item) => $photos->contains($item->id));
    }

    public function refreshCompletion(ClosingPhotoReportSubmission $submission): void
    {
        $photosComplete = $this->arePhotosComplete($submission);
        $fridgeRequired = $this->activeFridgeConfigs((int) $submission->tenant_id)->isNotEmpty();
        $fridgeComplete = ! $fridgeRequired || $submission->fridge_counted_at !== null;

        $complete = $photosComplete && $fridgeComplete;
        $submission->completed_at = $complete ? ($submission->completed_at ?: now()) : null;
    }

    /**
     * حفظ أعداد التلاجة الفعلية وتسوية المخزون المسجّل حسب الواقع.
     *
     * @param  array<int, array{config_id: int, actual_qty: float|int|string}>  $counts
     * @return array<string, mixed>
     */
    public function saveFridgeCounts(ClosingPhotoReportSubmission $submission, array $counts, User $user): array
    {
        if (! $this->arePhotosComplete($submission)) {
            throw ValidationException::withMessages([
                'fridge' => 'يجب إكمال رفع صور التقفيلة أولاً قبل إدخال أعداد التلاجة.',
            ]);
        }

        if ($submission->fridge_counted_at) {
            throw ValidationException::withMessages([
                'fridge' => 'تم حفظ جرد التلاجة مسبقاً لهذه التقفيلة. لا يمكن إعادة الحفظ حتى لا تُخفى فروقات العجز/الزيادة.',
            ]);
        }

        $configs = $this->activeFridgeConfigs((int) $submission->tenant_id);
        if ($configs->isEmpty()) {
            throw ValidationException::withMessages([
                'fridge' => 'لا توجد منتجات تلاجة نشطة لإدخال أعدادها.',
            ]);
        }

        $byConfigId = collect($counts)->keyBy(fn ($row) => (int) ($row['config_id'] ?? 0));
        foreach ($configs as $config) {
            if (! $byConfigId->has($config->id)) {
                throw ValidationException::withMessages([
                    'fridge' => 'يجب إدخال العدد الفعلي لكل منتجات التلاجة النشطة.',
                ]);
            }
            if (! is_numeric($byConfigId[$config->id]['actual_qty'] ?? null)) {
                throw ValidationException::withMessages([
                    'counts' => 'أدخل عدداً صالحاً لكل منتج.',
                ]);
            }
        }

        return DB::transaction(function () use ($submission, $configs, $byConfigId, $user) {
            $stocks = BranchFridgeStock::query()
                ->where('branch_id', $submission->branch_id)
                ->get()
                ->keyBy(fn (BranchFridgeStock $s) => $s->product_id.'|'.($s->size ?? ''));

            $saved = [];

            foreach ($configs as $config) {
                $key = $config->product_id.'|'.($config->size ?? '');
                $systemQty = (float) ($stocks->get($key)?->quantity ?? 0);
                $actualQty = (float) $byConfigId[$config->id]['actual_qty'];
                $diffQty = round($actualQty - $systemQty, 4);
                $unitPrice = $this->unitPriceForProduct($config->product, $config->size ?? '');
                $diffValue = round($diffQty * $unitPrice, 2);

                $row = ClosingPhotoReportFridgeCount::query()->updateOrCreate(
                    [
                        'submission_id' => $submission->id,
                        'fridge_product_config_id' => $config->id,
                    ],
                    [
                        'product_id' => $config->product_id,
                        'size' => $config->size ?? '',
                        'product_name' => $config->product?->name,
                        'system_qty' => $systemQty,
                        'actual_qty' => $actualQty,
                        'diff_qty' => $diffQty,
                    ]
                );

                BranchFridgeStock::query()->updateOrCreate(
                    [
                        'branch_id' => $submission->branch_id,
                        'product_id' => $config->product_id,
                        'size' => $config->size ?? '',
                    ],
                    [
                        'tenant_id' => $submission->tenant_id,
                        'quantity' => $actualQty,
                    ]
                );

                $saved[] = [
                    'id' => $row->id,
                    'config_id' => $config->id,
                    'product_id' => $config->product_id,
                    'product_name' => $config->product?->name ?? $row->product_name,
                    'size' => $config->size ?? '',
                    'system_qty' => $systemQty,
                    'actual_qty' => $actualQty,
                    'diff_qty' => $diffQty,
                    'unit_price' => $unitPrice,
                    'diff_value' => $diffValue,
                ];
            }

            ClosingPhotoReportFridgeCount::query()
                ->where('submission_id', $submission->id)
                ->whereNotIn('fridge_product_config_id', $configs->pluck('id'))
                ->delete();

            if (! $submission->submitted_by) {
                $submission->submitted_by = $user->id;
            }

            $submission->fridge_counted_at = now();
            $this->refreshCompletion($submission);
            $submission->save();

            return [
                'counts' => $saved,
                'summary' => $this->buildFridgeSummaryFromRows(collect($saved)),
                'fridge_counted_at' => optional($submission->fridge_counted_at)?->toDateTimeString(),
            ];
        });
    }

    public function formPayload(Tenant $tenant, int $branchId, string $closingType, string $businessDate): array
    {
        $items = $this->activeItems($tenant->id, $closingType);
        $submission = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branchId)
            ->where('closing_type', $closingType)
            ->whereDate('business_date', $businessDate)
            ->with(['photos.item', 'fridgeCounts.product:id,name,size_variants'])
            ->first();

        $photosByItem = collect($submission?->photos ?? [])->keyBy('item_id');
        $fridge = $this->fridgePayload($tenant, $branchId, $submission);

        $photosComplete = $submission ? $this->arePhotosComplete($submission) : false;

        return [
            'submission' => $submission ? [
                'id' => $submission->id,
                'completed_at' => optional($submission->completed_at)?->toDateTimeString(),
                'fridge_counted_at' => optional($submission->fridge_counted_at)?->toDateTimeString(),
                'is_complete' => $submission->isComplete(),
                'photos_complete' => $photosComplete,
            ] : null,
            'items' => $items->map(function (ClosingPhotoReportItem $item) use ($photosByItem) {
                $photo = $photosByItem->get($item->id);

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'is_required' => $item->is_required,
                    'sort_order' => $item->sort_order,
                    'photo' => $photo ? [
                        'id' => $photo->id,
                        'url' => $photo->url,
                        'original_name' => $photo->original_name,
                        'uploaded_at' => optional($photo->updated_at)?->toDateTimeString(),
                    ] : null,
                ];
            })->values()->all(),
            'fridge' => $fridge,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fridgePayload(Tenant $tenant, int $branchId, ?ClosingPhotoReportSubmission $submission): array
    {
        $configs = $this->activeFridgeConfigs((int) $tenant->id);
        $stocks = BranchFridgeStock::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (BranchFridgeStock $s) => $s->product_id.'|'.($s->size ?? ''));

        $savedByConfig = collect($submission?->fridgeCounts ?? [])->keyBy('fridge_product_config_id');

        $products = $configs->map(function (FridgeProductConfig $config) use ($stocks, $savedByConfig) {
            $key = $config->product_id.'|'.($config->size ?? '');
            $registered = (float) ($stocks->get($key)?->quantity ?? 0);
            $saved = $savedByConfig->get($config->id);

            return [
                'config_id' => $config->id,
                'product_id' => $config->product_id,
                'product_name' => $config->product?->name ?? '—',
                'size' => $config->size ?? '',
                'registered_qty' => $registered,
                'exclude_from_closing_count' => (bool) $config->exclude_from_closing_count,
                'actual_qty' => $saved ? (float) $saved->actual_qty : null,
                'system_qty_at_count' => $saved ? (float) $saved->system_qty : null,
                'diff_qty' => $saved ? (float) $saved->diff_qty : null,
            ];
        })->values()->all();

        $summary = null;
        if ($submission?->fridge_counted_at && $savedByConfig->isNotEmpty()) {
            $summary = $this->buildFridgeSummaryFromRows(
                $savedByConfig->map(function ($row) {
                    $product = $row->product ?? Product::query()->find($row->product_id);
                    $unitPrice = $this->unitPriceForProduct($product, $row->size ?? '');
                    $diffQty = (float) $row->diff_qty;

                    return [
                        'config_id' => $row->fridge_product_config_id,
                        'product_name' => $row->product_name ?: ($product?->name ?? '—'),
                        'size' => $row->size,
                        'system_qty' => (float) $row->system_qty,
                        'actual_qty' => (float) $row->actual_qty,
                        'diff_qty' => $diffQty,
                        'unit_price' => $unitPrice,
                        'diff_value' => round($diffQty * $unitPrice, 2),
                    ];
                })
            );
        }

        return [
            'required' => $configs->isNotEmpty(),
            'counted' => $submission?->fridge_counted_at !== null,
            'counted_at' => optional($submission?->fridge_counted_at)?->toDateTimeString(),
            'products' => $products,
            'summary' => $summary,
        ];
    }

    /**
     * ملخص جرد التلاجة لعرضه في تقرير المبيعات (حسب إعداد المستأجر).
     *
     * @param  array<int, int>|null  $branchIds
     * @return list<array<string, mixed>>
     */
    public function salesReportFridgeClosings(
        Tenant $tenant,
        string $dateFrom,
        ?string $dateTo = null,
        ?array $branchIds = null,
    ): array {
        if (! $tenant->closing_fridge_show_in_sales_report) {
            return [];
        }

        if ($this->activeFridgeConfigs((int) $tenant->id)->isEmpty()) {
            return [];
        }

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo ?: $dateFrom)->startOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $dates = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        $branchesQuery = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name');

        if ($branchIds !== null) {
            $branchesQuery->whereIn('id', array_values(array_filter($branchIds)));
        }

        $branches = $branchesQuery->get(['id', 'name']);
        if ($branches->isEmpty() || $dates === []) {
            return [];
        }

        $types = [
            ClosingPhotoReportItem::TYPE_EVENING,
            ClosingPhotoReportItem::TYPE_DAWN,
        ];

        $submissions = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('branch_id', $branches->pluck('id'))
            ->whereIn('closing_type', $types)
            ->whereBetween('business_date', [$dates[0], $dates[count($dates) - 1]])
            ->with(['fridgeCounts.product:id,name,size_variants'])
            ->get()
            ->keyBy(fn (ClosingPhotoReportSubmission $s) => $s->branch_id.'|'.$s->closing_type.'|'.optional($s->business_date)->toDateString());

        $includeEmptySlots = count($dates) === 1;
        $rows = [];

        foreach ($branches as $branch) {
            foreach ($dates as $date) {
                foreach ($types as $type) {
                    $submission = $submissions->get($branch->id.'|'.$type.'|'.$date);
                    if (! $includeEmptySlots && ! $submission) {
                        continue;
                    }

                    $counted = $submission?->fridge_counted_at !== null;
                    $summary = null;
                    if ($counted && $submission) {
                        $summary = $this->buildFridgeSummaryFromRows(
                            $submission->fridgeCounts->map(function ($row) {
                                $unitPrice = $this->unitPriceForProduct($row->product, $row->size ?? '');
                                $diffQty = (float) $row->diff_qty;

                                return [
                                    'config_id' => $row->fridge_product_config_id,
                                    'product_name' => $row->product_name ?: ($row->product?->name ?? '—'),
                                    'size' => $row->size,
                                    'system_qty' => (float) $row->system_qty,
                                    'actual_qty' => (float) $row->actual_qty,
                                    'diff_qty' => $diffQty,
                                    'unit_price' => $unitPrice,
                                    'diff_value' => round($diffQty * $unitPrice, 2),
                                ];
                            })
                        );
                    }

                    $rows[] = [
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'business_date' => $date,
                        'closing_type' => $type,
                        'closing_type_label' => $this->typeLabel($type),
                        'counted' => $counted,
                        'counted_at' => optional($submission?->fridge_counted_at)?->toDateTimeString(),
                        'summary' => $summary,
                    ];
                }
            }
        }

        return $rows;
    }

    private function unitPriceForProduct(?Product $product, string $size): float
    {
        $variants = collect($product?->size_variants ?? []);
        if ($variants->isEmpty()) {
            return 0.0;
        }

        if ($size !== '') {
            $variant = $variants->firstWhere('size', $size);

            return (float) ($variant['price'] ?? 0);
        }

        return (float) ($variants->first()['price'] ?? 0);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function buildFridgeSummaryFromRows(Collection $rows): array
    {
        $surplusQty = 0.0;
        $shortageQty = 0.0;
        $surplusValue = 0.0;
        $shortageValue = 0.0;
        $surplusItems = [];
        $shortageItems = [];

        foreach ($rows as $row) {
            $diffQty = (float) ($row['diff_qty'] ?? 0);
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            $diffValue = array_key_exists('diff_value', $row)
                ? (float) $row['diff_value']
                : round($diffQty * $unitPrice, 2);

            $item = [
                'config_id' => $row['config_id'] ?? null,
                'product_name' => $row['product_name'] ?? '—',
                'size' => $row['size'] ?? '',
                'system_qty' => (float) ($row['system_qty'] ?? 0),
                'actual_qty' => (float) ($row['actual_qty'] ?? 0),
                'diff_qty' => $diffQty,
                'unit_price' => $unitPrice,
                'diff_value' => $diffValue,
            ];

            if ($diffQty > 0.0001) {
                $surplusQty += $diffQty;
                $surplusValue += $diffValue;
                $surplusItems[] = $item;
            } elseif ($diffQty < -0.0001) {
                $shortageQty += abs($diffQty);
                $shortageValue += abs($diffValue);
                $shortageItems[] = $item;
            }
        }

        usort($surplusItems, fn ($a, $b) => abs($b['diff_value']) <=> abs($a['diff_value']));
        usort($shortageItems, fn ($a, $b) => abs($b['diff_value']) <=> abs($a['diff_value']));

        return [
            'surplus_total' => round($surplusQty, 4),
            'shortage_total' => round($shortageQty, 4),
            'surplus_value_total' => round($surplusValue, 2),
            'shortage_value_total' => round($shortageValue, 2),
            'net_value' => round($surplusValue - $shortageValue, 2),
            'matched_count' => $rows->filter(fn ($r) => abs((float) ($r['diff_qty'] ?? 0)) <= 0.0001)->count(),
            'variance_count' => count($surplusItems) + count($shortageItems),
            'surplus_items' => $surplusItems,
            'shortage_items' => $shortageItems,
        ];
    }

    private function typeLabel(string $closingType): string
    {
        return ClosingPhotoReportItem::typeLabel($closingType);
    }

    private function tenantFor(User $user): ?Tenant
    {
        if (! $user->tenant_id) {
            return null;
        }

        return Tenant::query()->find($user->tenant_id);
    }

    private function normalizeTime(mixed $value, string $fallback): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $raw)) {
            return $raw.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}/', $raw)) {
            return substr($raw, 0, 8);
        }

        return $fallback;
    }

    private function timeInRange(string $time, string $start, string $end): bool
    {
        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        return $time >= $start || $time <= $end;
    }

    private function windowMeta(Tenant $tenant, string $closingType): array
    {
        if ($closingType === ClosingPhotoReportItem::TYPE_EVENING) {
            return [
                'starts_at' => $this->normalizeTime($tenant->closing_evening_starts_at, '17:00:00'),
                'ends_at' => $this->normalizeTime($tenant->closing_evening_ends_at, '23:59:59'),
            ];
        }

        return [
            'starts_at' => $this->normalizeTime($tenant->closing_dawn_starts_at, '00:00:00'),
            'ends_at' => $this->normalizeTime($tenant->closing_dawn_ends_at, '06:59:59'),
        ];
    }
}
