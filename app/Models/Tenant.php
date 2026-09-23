<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'closing_photo_reports_enabled',
        'closing_evening_starts_at',
        'closing_evening_ends_at',
        'closing_dawn_starts_at',
        'closing_dawn_ends_at',
        'closing_fridge_show_in_sales_report',
        'fixed_salary_withdraw_limit_enabled',
        'fixed_salary_early_withdraw_percent',
        'fixed_salary_full_unlock_day',
    ];

    protected $casts = [
        'closing_photo_reports_enabled' => 'boolean',
        'closing_fridge_show_in_sales_report' => 'boolean',
        'fixed_salary_withdraw_limit_enabled' => 'boolean',
        'fixed_salary_early_withdraw_percent' => 'float',
        'fixed_salary_full_unlock_day' => 'integer',
    ];

    /**
     * المستخدمون التابعون لهذا الـ tenant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function closingPhotoReportItems(): HasMany
    {
        return $this->hasMany(ClosingPhotoReportItem::class);
    }

    /**
     * إنشاء slug تلقائي من الاسم إذا لم يُحدد
     */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->slug) && !empty($tenant->name)) {
                $tenant->slug = \Illuminate\Support\Str::slug($tenant->name);
            }
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->logo_path)) {
            return null;
        }

        return asset('storage/'.$this->logo_path);
    }

    /**
     * هل فتح الراتب الكامل في التاريخ المحدد لهذا الشهر؟
     */
    public function isFixedSalaryFullyUnlockedForDate(Carbon $date): bool
    {
        if (! $this->fixed_salary_withdraw_limit_enabled) {
            return true;
        }

        $unlockDay = (int) ($this->fixed_salary_full_unlock_day ?: 29);
        $unlockDay = max(1, min(31, $unlockDay));
        $effectiveDay = min($unlockDay, $date->daysInMonth);

        return $date->day >= $effectiveDay;
    }

    /**
     * سقف السحب المبكر = نسبة من الراتب الثابت.
     */
    public function fixedSalaryEarlyWithdrawCap(float $fixedSalary): float
    {
        $percent = (float) ($this->fixed_salary_early_withdraw_percent ?? 0);

        return round(max(0, $fixedSalary) * (max(0, min(100, $percent)) / 100), 2);
    }
}
