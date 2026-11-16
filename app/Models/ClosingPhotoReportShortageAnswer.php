<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingPhotoReportShortageAnswer extends Model
{
    public const SOURCE_RAW = 'raw';

    public const SOURCE_CUSTOM = 'custom';

    public const STATUS_NEEDED = 'needed';

    public const STATUS_NOT_NEEDED = 'not_needed';

    protected $fillable = [
        'submission_id',
        'source',
        'product_id',
        'custom_item_id',
        'title_snapshot',
        'category_name',
        'status',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ClosingPhotoReportSubmission::class, 'submission_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customItem(): BelongsTo
    {
        return $this->belongsTo(ClosingShortageCustomItem::class, 'custom_item_id');
    }

    public function isNeeded(): bool
    {
        return $this->status === self::STATUS_NEEDED;
    }
}
