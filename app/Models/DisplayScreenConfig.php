<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DisplayScreenConfig extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'display_screen_config';

    protected $fillable = ['interval_seconds', 'tenant_id'];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}
