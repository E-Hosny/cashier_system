<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * ميزات الـ tenant التي تتحكم في ظهور مجموعات الصلاحيات.
 * الوحدات الأساسية دائماً مفعّلة؛ الميزات الاختيارية تُقرأ من أعمدة الـ tenant.
 */
class TenantFeatures
{
    /**
     * @return array<string, bool>
     */
    public static function for(?Tenant $tenant): array
    {
        return [
            // وحدات أساسية — متاحة لكل tenant
            'products' => true,
            'offers' => true,
            'inventory' => true,
            'cashier' => true,
            'purchases' => true,
            'expenses' => true,
            'sales' => true,
            'employees' => true,
            'users' => true,
            'branches' => true,
            'display' => true,
            'feedback' => true,
            'job_applications' => true,
            'barista' => true,
            'tenant_settings' => true,

            // ميزات اختيارية مربوطة بإعدادات الـ tenant
            'closing_photos' => (bool) ($tenant?->closing_photo_reports_enabled),
        ];
    }

    /**
     * هل أي tenant في النظام مفعّل لهذه الميزة؟
     * تُستخدم عند منح الصلاحيات الافتراضية للأدوار المشتركة عالمياً.
     */
    public static function anyTenantHas(string $feature): bool
    {
        return match ($feature) {
            'closing_photos' => Tenant::query()->where('closing_photo_reports_enabled', true)->exists(),
            default => true,
        };
    }
}
