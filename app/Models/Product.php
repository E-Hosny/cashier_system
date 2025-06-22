<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;



class Product extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'name', 
        'quantity', 
        'image', 
        'category_id', 
        'tenant_id',
        'size_variants'
    ];

    protected $casts = [
        'size_variants' => 'array',
    ];

    public function tenant()
{
    return $this->belongsTo(User::class, 'tenant_id');
}

protected static function booted()
{
    static::addGlobalScope('tenant', function (Builder $query) {
        if (auth()->check()) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }
    });

    static::creating(function ($model) {
        if (auth()->check()) {
            $model->tenant_id = auth()->user()->tenant_id;
        }
    });
}

public function category()
{
    return $this->belongsTo(Category::class);
}

public function getAvailableSizesAttribute()
{
    if (empty($this->size_variants)) {
        return [];
    }
    return collect($this->size_variants)->pluck('size')->toArray();
}

public function getSizesInArabicAttribute()
{
    if (empty($this->size_variants)) {
        return 'غير محدد';
    }

    $sizeTranslations = [
        'small' => 'صغير',
        'medium' => 'وسط',
        'large' => 'كبير',
    ];

    return collect($this->size_variants)
        ->map(function ($variant) use ($sizeTranslations) {
            $size = $sizeTranslations[$variant['size']] ?? $variant['size'];
            return "{$size}: {$variant['price']}";
        })
        ->implode('، ');
}

}
