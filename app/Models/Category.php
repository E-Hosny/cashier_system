<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_RAW = 'raw';

    protected $fillable = ['name', 'tenant_id', 'scope'];

    public function scopeForProducts($query)
    {
        return $query->where('scope', self::SCOPE_PRODUCT);
    }

    public function scopeForRawMaterials($query)
    {
        return $query->where('scope', self::SCOPE_RAW);
    }

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
