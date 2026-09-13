<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ClosingPhotoReportItem;
use App\Models\ClosingPhotoReportPhoto;
use App\Models\ClosingPhotoReportSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
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
            'reason' => 'يجب رفع صور '.$requirement['closing_type_label'].' ليوم '.$requirement['business_date'].' قبل فتح تقارير المبيعات.',
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
                'reason' => 'يجب تحديد الفرع وإكمال صور التقفيلة قبل عرض المبيعات.',
            ];
        }

        $requirement = $this->currentRequirement($tenant, (int) $branchId);
        if (! $requirement || $requirement['is_complete']) {
            return null;
        }

        return [
            'blocked' => true,
            'reason' => 'المبيعات مخفية حتى يتم رفع صور '.$requirement['closing_type_label'].' ليوم '.$requirement['business_date'].'.',
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
            ->with(['photos'])
            ->first();

        $photosByItem = collect($submission?->photos ?? [])->keyBy('item_id');
        $requiredItems = $items->where('is_required', true);
        $uploadedRequired = $requiredItems->filter(fn ($item) => $photosByItem->has($item->id))->count();
        $isComplete = $submission?->completed_at !== null
            || ($requiredItems->isNotEmpty() && $uploadedRequired === $requiredItems->count());

        // إن اكتملت الصور لكن لم تُعلَّم completed_at، نحدّثها
        if ($isComplete && $submission && ! $submission->completed_at) {
            $submission->forceFill(['completed_at' => now()])->save();
        }

        $branch = Branch::withoutGlobalScopes()->find($branchId);

        return [
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'branch_name' => $branch?->name,
            'closing_type' => $closingType,
            'closing_type_label' => ClosingPhotoReportItem::typeLabel($closingType),
            'business_date' => $businessDate,
            'is_complete' => (bool) $isComplete,
            'required_count' => $requiredItems->count(),
            'uploaded_required_count' => $uploadedRequired,
            'items_count' => $items->count(),
            'submission_id' => $submission?->id,
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

    public function refreshCompletion(ClosingPhotoReportSubmission $submission): void
    {
        $items = $this->activeItems((int) $submission->tenant_id, $submission->closing_type);
        $required = $items->where('is_required', true);
        $photos = ClosingPhotoReportPhoto::query()
            ->where('submission_id', $submission->id)
            ->pluck('item_id');

        $complete = $required->isNotEmpty()
            && $required->every(fn ($item) => $photos->contains($item->id));

        $submission->completed_at = $complete ? ($submission->completed_at ?: now()) : null;
    }

    public function formPayload(Tenant $tenant, int $branchId, string $closingType, string $businessDate): array
    {
        $items = $this->activeItems($tenant->id, $closingType);
        $submission = ClosingPhotoReportSubmission::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('branch_id', $branchId)
            ->where('closing_type', $closingType)
            ->whereDate('business_date', $businessDate)
            ->with(['photos.item'])
            ->first();

        $photosByItem = collect($submission?->photos ?? [])->keyBy('item_id');

        return [
            'submission' => $submission ? [
                'id' => $submission->id,
                'completed_at' => optional($submission->completed_at)?->toDateTimeString(),
                'is_complete' => $submission->isComplete(),
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
        ];
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

        // نافذة تعبر منتصف الليل (احتياطي)
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
