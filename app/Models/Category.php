<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['name', 'tenant_id'];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
