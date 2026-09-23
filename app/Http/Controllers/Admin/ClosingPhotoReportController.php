<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ClosingPhotoReportItem;
use App\Models\ClosingPhotoReportSubmission;
use App\Models\Tenant;
use App\Services\ClosingPhotoReportService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClosingPhotoReportController extends Controller
{
    public function __construct(private ClosingPhotoReportService $service)
    {
    }

    /**
     * إعدادات وبنود التقفيلة (سوبر أدمن).
     */
    public function settings(): Response
    {
        $this->assertSuperAdmin();
        $tenant = $this->currentTenant();

        $items = ClosingPhotoReportItem::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('closing_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('closing_type');

        $recent = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['branch:id,name', 'submittedBy:id,name', 'photos.item:id,title'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (ClosingPhotoReportSubmission $s) => [
                'id' => $s->id,
                'branch_name' => $s->branch?->name,
                'closing_type' => $s->closing_type,
                'closing_type_label' => ClosingPhotoReportItem::typeLabel($s->closing_type),
                'business_date' => optional($s->business_date)?->toDateString(),
                'is_complete' => $s->isComplete(),
                'completed_at' => optional($s->completed_at)?->toDateTimeString(),
                'submitted_by_name' => $s->submittedBy?->name,
                'photos_count' => $s->photos->count(),
                'photos' => $s->photos->map(fn ($p) => [
                    'id' => $p->id,
                    'item_title' => $p->item?->title,
                    'url' => $p->url,
                ])->values()->all(),
            ]);

        return Inertia::render('Admin/ClosingPhotoReports/Settings', [
            'settings' => [
                'enabled' => (bool) $tenant->closing_photo_reports_enabled,
                'show_fridge_in_sales_report' => (bool) $tenant->closing_fridge_show_in_sales_report,
                'evening_starts_at' => substr((string) $tenant->closing_evening_starts_at, 0, 5) ?: '17:00',
                'evening_ends_at' => substr((string) $tenant->closing_evening_ends_at, 0, 5) ?: '23:59',
                'dawn_starts_at' => substr((string) $tenant->closing_dawn_starts_at, 0, 5) ?: '00:00',
                'dawn_ends_at' => substr((string) $tenant->closing_dawn_ends_at, 0, 5) ?: '06:59',
            ],
            'itemsByType' => [
                'evening' => ($items->get(ClosingPhotoReportItem::TYPE_EVENING) ?? collect())->values(),
                'dawn' => ($items->get(ClosingPhotoReportItem::TYPE_DAWN) ?? collect())->values(),
            ],
            'recentSubmissions' => $recent,
            'typeLabels' => [
                'evening' => ClosingPhotoReportItem::typeLabel(ClosingPhotoReportItem::TYPE_EVENING),
                'dawn' => ClosingPhotoReportItem::typeLabel(ClosingPhotoReportItem::TYPE_DAWN),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->assertSuperAdmin();
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'show_fridge_in_sales_report' => ['required', 'boolean'],
            'evening_starts_at' => ['required', 'date_format:H:i'],
            'evening_ends_at' => ['required', 'date_format:H:i'],
            'dawn_starts_at' => ['required', 'date_format:H:i'],
            'dawn_ends_at' => ['required', 'date_format:H:i'],
        ]);

        $tenant->update([
            'closing_photo_reports_enabled' => $data['enabled'],
            'closing_fridge_show_in_sales_report' => $data['show_fridge_in_sales_report'],
            'closing_evening_starts_at' => $data['evening_starts_at'].':00',
            'closing_evening_ends_at' => $data['evening_ends_at'].':59',
            'closing_dawn_starts_at' => $data['dawn_starts_at'].':00',
            'closing_dawn_ends_at' => $data['dawn_ends_at'].':59',
        ]);

        return back()->with('success', 'تم حفظ إعدادات تقارير صور التقفيلة.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->assertSuperAdmin();
        $tenant = $this->currentTenant();

        $data = $request->validate([
            'closing_type' => ['required', Rule::in([ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN])],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $maxSort = (int) ClosingPhotoReportItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('closing_type', $data['closing_type'])
            ->max('sort_order');

        ClosingPhotoReportItem::create([
            'tenant_id' => $tenant->id,
            'closing_type' => $data['closing_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
            'is_required' => $data['is_required'],
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'تم إضافة البند.');
    }

    public function updateItem(Request $request, ClosingPhotoReportItem $item): RedirectResponse
    {
        $this->assertSuperAdmin();
        $this->assertItemTenant($item);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        $item->update($data);

        return back()->with('success', 'تم تحديث البند.');
    }

    public function destroyItem(ClosingPhotoReportItem $item): RedirectResponse
    {
        $this->assertSuperAdmin();
        $this->assertItemTenant($item);
        $item->delete();

        return back()->with('success', 'تم حذف البند.');
    }

    /**
     * عرض صور التقفيلة لكل الفروع (عرض فقط) — للسوبر أدمن / المدير / مسؤول الموظفين.
     */
    public function browse(Request $request): Response
    {
        $user = Auth::user();
        abort_unless(
            $user?->hasRole('super admin')
            || $user?->hasRole('admin')
            || $user?->isHrOnly(),
            403
        );

        $tenant = $this->currentTenant();
        $businessDate = $request->input('business_date')
            ?: $this->service->businessDateFor()->toDateString();
        $closingType = $request->input('closing_type');
        $branchId = $request->integer('branch_id') ?: null;

        $branches = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // المدير العادي يرى فرعه فقط إن لم يكن سوبر أدمن / HR
        if ($user->hasRole('admin') && ! $user->hasRole('super admin') && ! $user->isHrOnly()) {
            $ownBranchId = BranchContext::id() ?: $user->branch_id;
            $branches = $branches->where('id', (int) $ownBranchId)->values();
            $branchId = $ownBranchId ? (int) $ownBranchId : null;
        }

        if ($branchId) {
            $branches = $branches->where('id', $branchId)->values();
        }

        $types = [];
        if ($closingType && in_array($closingType, [ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN], true)) {
            $types = [$closingType];
        } else {
            $types = [ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN];
        }

        $itemsByType = ClosingPhotoReportItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('closing_type', $types)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'closing_type', 'title', 'description', 'sort_order', 'is_required'])
            ->groupBy('closing_type');

        $submissions = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('business_date', $businessDate)
            ->whereIn('closing_type', $types)
            ->when($branches->isNotEmpty(), fn ($q) => $q->whereIn('branch_id', $branches->pluck('id')))
            ->with([
                'branch:id,name',
                'submittedBy:id,name',
                'photos',
                'fridgeCounts.product:id,name,size_variants',
            ])
            ->get()
            ->keyBy(fn (ClosingPhotoReportSubmission $s) => $s->branch_id.'|'.$s->closing_type);

        $rows = collect();
        $fridgeRequiredGlobally = $this->service->activeFridgeConfigs((int) $tenant->id)->isNotEmpty();

        foreach ($branches as $branch) {
            foreach ($types as $type) {
                $items = collect($itemsByType->get($type, []));
                if ($items->isEmpty()) {
                    continue;
                }

                $submission = $submissions->get($branch->id.'|'.$type);
                $photosByItem = collect($submission?->photos ?? [])->keyBy('item_id');

                $checklist = $items->map(function (ClosingPhotoReportItem $item) use ($photosByItem) {
                    $photo = $photosByItem->get($item->id);

                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'description' => $item->description,
                        'is_required' => (bool) $item->is_required,
                        'is_uploaded' => $photo !== null,
                        'photo' => $photo ? [
                            'id' => $photo->id,
                            'url' => $photo->url,
                            'original_name' => $photo->original_name,
                            'uploaded_at' => optional($photo->updated_at)?->toDateTimeString(),
                        ] : null,
                    ];
                })->values();

                $requiredItems = $checklist->where('is_required', true);
                $requiredTotal = $requiredItems->count();
                $requiredUploaded = $requiredItems->where('is_uploaded', true)->count();
                $requiredRemaining = max(0, $requiredTotal - $requiredUploaded);
                $optionalUploaded = $checklist->where('is_required', false)->where('is_uploaded', true)->count();
                $uploadedTotal = $checklist->where('is_uploaded', true)->count();
                $photosComplete = $requiredTotal > 0 && $requiredRemaining === 0;

                $fridge = $this->service->fridgePayload($tenant, (int) $branch->id, $submission);
                $fridgeRequired = (bool) ($fridge['required'] ?? $fridgeRequiredGlobally);
                $fridgeCounted = (bool) ($fridge['counted'] ?? false);
                $isComplete = $photosComplete && (! $fridgeRequired || $fridgeCounted);

                if ($isComplete) {
                    $statusLabel = 'مكتمل';
                    $statusDetail = "تم إرسال كل البنود الإجبارية ({$requiredUploaded}/{$requiredTotal})";
                    if ($fridgeRequired) {
                        $statusDetail .= ' · جرد التلاجة تم';
                    }
                } elseif ($uploadedTotal === 0 && ! $fridgeCounted) {
                    $statusLabel = 'لم يبدأ';
                    $statusDetail = "لم يُرسل أي بند بعد — متبقي {$requiredRemaining} إجباري";
                    if ($fridgeRequired) {
                        $statusDetail .= ' · وجرد التلاجة';
                    }
                } else {
                    $statusLabel = 'جارٍ الاستكمال';
                    $parts = [];
                    if (! $photosComplete) {
                        $parts[] = "صور: {$requiredUploaded}/{$requiredTotal} إجباري";
                    } else {
                        $parts[] = 'الصور مكتملة';
                    }
                    if ($fridgeRequired) {
                        $parts[] = $fridgeCounted ? 'جرد التلاجة تم' : 'جرد التلاجة متبقي';
                    }
                    $statusDetail = implode(' · ', $parts);
                }

                if ($optionalUploaded > 0) {
                    $statusDetail .= " · اختياري مرفوع: {$optionalUploaded}";
                }

                $rows->push([
                    'id' => $submission?->id ?: ('pending-'.$branch->id.'-'.$type),
                    'submission_id' => $submission?->id,
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'closing_type' => $type,
                    'closing_type_label' => ClosingPhotoReportItem::typeLabel($type),
                    'business_date' => $businessDate,
                    'is_complete' => $isComplete,
                    'photos_complete' => $photosComplete,
                    'completed_at' => optional($submission?->completed_at)?->toDateTimeString(),
                    'submitted_by_name' => $submission?->submittedBy?->name,
                    'status_label' => $statusLabel,
                    'status_detail' => $statusDetail,
                    'required_total' => $requiredTotal,
                    'required_uploaded' => $requiredUploaded,
                    'required_remaining' => $requiredRemaining,
                    'uploaded_total' => $uploadedTotal,
                    'items' => $checklist->all(),
                    'fridge' => $fridge,
                ]);
            }
        }

        // المكتمل أولاً؟ لا — الأهم للمتابعة: غير المكتمل أولاً
        $rows = $rows
            ->sortBy([
                fn ($row) => $row['is_complete'] ? 1 : 0,
                fn ($row) => $row['branch_name'] ?? '',
                fn ($row) => $row['closing_type'] === ClosingPhotoReportItem::TYPE_EVENING ? 0 : 1,
            ])
            ->values();

        return Inertia::render('Admin/ClosingPhotoReports/Browse', [
            'filters' => [
                'business_date' => $businessDate,
                'closing_type' => $closingType ?: '',
                'branch_id' => $branchId ? (string) $branchId : '',
            ],
            'branches' => $branches->values(),
            'submissions' => $rows,
            'typeLabels' => [
                'evening' => ClosingPhotoReportItem::typeLabel(ClosingPhotoReportItem::TYPE_EVENING),
                'dawn' => ClosingPhotoReportItem::typeLabel(ClosingPhotoReportItem::TYPE_DAWN),
            ],
            'canFilterAllBranches' => $user->hasRole('super admin') || $user->isHrOnly(),
            'readOnly' => true,
            'enabled' => (bool) $tenant->closing_photo_reports_enabled,
        ]);
    }

    /**
     * شاشة المدير لرفع صور التقفيلة الحالية.
     */
    public function submitForm(Request $request): Response|RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user?->hasRole('admin') || $user?->hasRole('super admin'), 403);

        $tenant = $this->currentTenant();
        if (! $tenant->closing_photo_reports_enabled && ! $user->hasRole('super admin')) {
            return redirect()->route('admin.sales.report');
        }

        $branchId = BranchContext::id() ?: $user->branch_id;
        abort_unless($branchId, 422, 'يجب تحديد الفرع أولاً.');

        $branch = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($branchId);

        $closingType = $request->input('closing_type')
            ?: $this->service->resolveClosingType($tenant)
            ?: ClosingPhotoReportItem::TYPE_EVENING;

        abort_unless(in_array($closingType, [ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN], true), 422);

        $businessDate = $request->input('business_date')
            ?: $this->service->businessDateFor()->toDateString();

        $payload = $this->service->formPayload($tenant, (int) $branch->id, $closingType, $businessDate);
        $requirement = $this->service->currentRequirement($tenant, (int) $branch->id);

        return Inertia::render('Admin/ClosingPhotoReports/Submit', [
            'branch' => ['id' => $branch->id, 'name' => $branch->name],
            'closingType' => $closingType,
            'closingTypeLabel' => ClosingPhotoReportItem::typeLabel($closingType),
            'businessDate' => $businessDate,
            'enabled' => (bool) $tenant->closing_photo_reports_enabled,
            'currentRequirement' => $requirement,
            'submission' => $payload['submission'],
            'items' => $payload['items'],
            'fridge' => $payload['fridge'],
            'canChooseType' => $user->hasRole('super admin'),
            'salesReportUrl' => route('admin.sales.report'),
        ]);
    }

    public function upload(Request $request)
    {
        $user = Auth::user();
        abort_unless($user?->hasRole('admin') || $user?->hasRole('super admin'), 403);

        $tenant = $this->currentTenant();
        abort_unless($tenant->closing_photo_reports_enabled || $user->hasRole('super admin'), 403);

        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:closing_photo_report_items,id'],
            'closing_type' => ['required', Rule::in([ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN])],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'file' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
        ]);

        $branchId = BranchContext::id() ?: $user->branch_id;
        abort_unless($branchId, 422, 'يجب تحديد الفرع أولاً.');

        $item = ClosingPhotoReportItem::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('id', $data['item_id'])
            ->where('closing_type', $data['closing_type'])
            ->where('is_active', true)
            ->firstOrFail();

        $submission = $this->service->getOrCreateSubmission(
            $tenant,
            (int) $branchId,
            $data['closing_type'],
            $data['business_date'],
            $user->id
        );

        $photo = $this->service->uploadPhoto($submission, $item, $request->file('file'), $user);
        $this->service->refreshCompletion($submission);
        $submission->save();

        $payload = $this->service->formPayload($tenant, (int) $branchId, $data['closing_type'], $data['business_date']);
        $photosComplete = (bool) ($payload['submission']['photos_complete'] ?? false);
        $fridgeRequired = (bool) ($payload['fridge']['required'] ?? false);
        $fridgeCounted = (bool) ($payload['fridge']['counted'] ?? false);

        if ($payload['submission']['is_complete'] ?? false) {
            $message = 'تم رفع الصورة واكتملت التقفيلة. يمكنك فتح تقارير المبيعات الآن.';
        } elseif ($photosComplete && $fridgeRequired && ! $fridgeCounted) {
            $message = 'تم رفع كل الصور المطلوبة. أكمل إدخال أعداد التلاجة الفعلية.';
        } else {
            $message = 'تم رفع الصورة.';
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'item_id' => $item->id,
                'photo' => [
                    'id' => $photo->id,
                    'url' => $photo->url,
                    'original_name' => $photo->original_name,
                    'uploaded_at' => optional($photo->updated_at)?->toDateTimeString(),
                ],
                'submission' => $payload['submission'],
                'items' => $payload['items'],
                'fridge' => $payload['fridge'],
                'is_complete' => (bool) ($payload['submission']['is_complete'] ?? false),
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * إدخال أعداد التلاجة الفعلية بعد الصور وتسوية المخزون.
     */
    public function storeFridgeCount(Request $request)
    {
        $user = Auth::user();
        abort_unless($user?->hasRole('admin') || $user?->hasRole('super admin'), 403);

        $tenant = $this->currentTenant();
        abort_unless($tenant->closing_photo_reports_enabled || $user->hasRole('super admin'), 403);

        $data = $request->validate([
            'closing_type' => ['required', Rule::in([ClosingPhotoReportItem::TYPE_EVENING, ClosingPhotoReportItem::TYPE_DAWN])],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.config_id' => ['required', 'integer', 'exists:fridge_product_configs,id'],
            'counts.*.actual_qty' => ['required', 'numeric'],
        ]);

        $branchId = BranchContext::id() ?: $user->branch_id;
        abort_unless($branchId, 422, 'يجب تحديد الفرع أولاً.');

        $submission = $this->service->getOrCreateSubmission(
            $tenant,
            (int) $branchId,
            $data['closing_type'],
            $data['business_date'],
            $user->id
        );

        $result = $this->service->saveFridgeCounts($submission, $data['counts'], $user);
        $payload = $this->service->formPayload($tenant, (int) $branchId, $data['closing_type'], $data['business_date']);

        $message = 'تم حفظ أعداد التلاجة وتسوية المخزون.';
        if ($payload['submission']['is_complete'] ?? false) {
            $message .= ' اكتملت التقفيلة ويمكن فتح تقارير المبيعات.';
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'result' => $result,
                'submission' => $payload['submission'],
                'fridge' => $payload['fridge'],
                'is_complete' => (bool) ($payload['submission']['is_complete'] ?? false),
            ]);
        }

        return back()->with('success', $message);
    }

    private function assertSuperAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('super admin'), 403);
    }

    private function assertItemTenant(ClosingPhotoReportItem $item): void
    {
        abort_unless((int) $item->tenant_id === (int) Auth::user()->tenant_id, 403);
    }

    private function currentTenant(): Tenant
    {
        $tenant = Tenant::query()->find(Auth::user()->tenant_id);
        abort_unless($tenant, 404, 'الحساب غير موجود.');

        return $tenant;
    }
}
