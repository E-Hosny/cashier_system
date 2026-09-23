<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingPhotoReportFridgeCount extends Model
{
    protected $fillable = [
        'submission_id',
        'fridge_product_config_id',
        'product_id',
        'size',
        'product_name',
        'system_qty',
        'actual_qty',
        'diff_qty',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'decimal:4',
            'actual_qty' => 'decimal:4',
            'diff_qty' => 'decimal:4',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ClosingPhotoReportSubmission::class, 'submission_id');
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(FridgeProductConfig::class, 'fridge_product_config_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
