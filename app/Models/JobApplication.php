<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'age',
        'note',
        'note_by_user_id',
        'note_updated_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'note_updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function noteAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'note_by_user_id');
    }
}
