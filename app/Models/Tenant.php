<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * المستخدمون التابعون لهذا الـ tenant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
}
