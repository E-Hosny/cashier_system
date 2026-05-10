<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
        static::creating(function (Branch $branch) {
            if (empty($branch->slug) && ! empty($branch->name)) {
                $branch->slug = \Illuminate\Support\Str::slug($branch->name.'-'.$branch->tenant_id);
            }
        });

        static::updating(function (Branch $branch) {
            if ($branch->isDirty('name') && $branch->tenant_id) {
                $branch->slug = \Illuminate\Support\Str::slug($branch->name.'-'.$branch->tenant_id);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
