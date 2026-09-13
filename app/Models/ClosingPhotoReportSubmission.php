<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClosingPhotoReportSubmission extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'closing_type',
        'business_date',
        'submitted_by',
        'completed_at',
    ];

    protected $casts = [
        'business_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
        static::bootBelongsToBranch();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function photos()
    {
        return $this->hasMany(ClosingPhotoReportPhoto::class, 'submission_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
