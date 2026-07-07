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
        'printer_settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'printer_settings' => 'array',
        ];
    }

    /**
     * @return array{mode: string, method: string, customer_printer: ?string, staff_printer: ?string}
     */
    public function normalizedPrinterSettings(): array
    {
        $settings = $this->printer_settings ?? [];

        return [
            'mode' => in_array($settings['mode'] ?? '', ['single', 'dual'], true) ? $settings['mode'] : 'single',
            'method' => in_array($settings['method'] ?? '', ['qz', 'browser'], true) ? $settings['method'] : 'browser',
            'customer_printer' => filled($settings['customer_printer'] ?? null) ? (string) $settings['customer_printer'] : null,
            'staff_printer' => filled($settings['staff_printer'] ?? null) ? (string) $settings['staff_printer'] : null,
        ];
    }

    public function usesDualPrinters(): bool
    {
        return $this->normalizedPrinterSettings()['mode'] === 'dual';
    }

    public function usesQzTray(): bool
    {
        return $this->normalizedPrinterSettings()['method'] === 'qz';
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
