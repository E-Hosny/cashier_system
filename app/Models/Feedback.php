<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'rating',
        'comment',
        'is_approved',
        'ip_address',
        'user_agent'
    ];

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

    public function getRatingStarsAttribute()
    {
        return str_repeat('⭐', $this->rating);
    }

    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('Y-m-d H:i');
    }
} 