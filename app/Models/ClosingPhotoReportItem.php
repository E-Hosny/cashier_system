<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClosingPhotoReportItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const TYPE_EVENING = 'evening';

    public const TYPE_DAWN = 'dawn';

    protected $fillable = [
        'tenant_id',
        'closing_type',
        'title',
        'description',
        'sort_order',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function photos()
    {
        return $this->hasMany(ClosingPhotoReportPhoto::class, 'item_id');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_EVENING => 'التقفيلة الأولى (مساءً)',
            self::TYPE_DAWN => 'التقفيلة الثانية (فجراً)',
            default => $type,
        };
    }
}
