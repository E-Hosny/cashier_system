<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'rating',
        'comment',
        'is_approved',
        'ip_address',
        'user_agent',
        'tenant_id',
        'branch_id',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeOrderByLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getRatingStarsAttribute()
    {
        return str_repeat('⭐', $this->rating);
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('Y-m-d H:i');
    }
} 