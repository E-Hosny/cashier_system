<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ClosingPhotoReportPhoto extends Model
{
    use HasFactory;

    public const DISK = 'spaces';

    protected $fillable = [
        'submission_id',
        'item_id',
        'path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = ['url'];

    public function submission()
    {
        return $this->belongsTo(ClosingPhotoReportSubmission::class, 'submission_id');
    }

    public function item()
    {
        return $this->belongsTo(ClosingPhotoReportItem::class, 'item_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        try {
            return Storage::disk(self::DISK)->url($this->path);
        } catch (\Throwable) {
            return null;
        }
    }
}
