<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'closing_photo_reports_enabled' => 'boolean',
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
}
