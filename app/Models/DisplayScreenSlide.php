<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DisplayScreenSlide extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['path', 'sort_order', 'duration_seconds', 'tenant_id'];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}
