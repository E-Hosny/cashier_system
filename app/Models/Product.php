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
        'type',
        'unit',
        'stock',
        'stock_alert_threshold',
        'quantity',
        'image',
        'category_id',
        'tenant_id',
        'size_variants',
        'purchase_unit',
        'purchase_quantity',
        'purchase_price',
        'consume_unit',
        'unit_consume_price',
    ];

    protected $casts = [
        'size_variants' => 'array',
        'unit_conversions' => 'array',
    ];

    public function ingredients()
    {
        return $this->belongsToMany(Product::class, 'ingredients', 'finished_product_id', 'raw_material_id')
                    ->withPivot('quantity_consumed', 'size');
    }

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

    /**
     * حساب سعر الوحدة المحددة
     */
    public function getUnitPrice($unit = null)
    {
        if ($this->type !== 'raw' || !$this->unit_consume_price) {
            return 0;
        }
        return $this->unit_consume_price;
    }

    /**
     * حساب تكلفة كمية محددة من المادة الخام
     */
    public function calculateCost($quantity, $unit = null)
    {
        $unitPrice = $this->getUnitPrice($unit);
        return $unitPrice * $quantity;
    }

    /**
     * حساب تكلفة مكونات منتج معين
     */
    public function calculateIngredientsCost($size = null)
    {
        if ($this->type !== 'finished') {
            return 0;
        }

        $totalCost = 0;
        $ingredients = $this->ingredients;

        if ($size) {
            $ingredients = $ingredients->where('pivot.size', $size);
        }

        foreach ($ingredients as $ingredient) {
            $cost = $ingredient->calculateCost($ingredient->pivot->quantity_consumed);
            $totalCost += $cost;
        }

        return $totalCost;
    }

    /**
     * الحصول على هامش الربح للمنتج
     */
    public function getProfitMargin($size = null)
    {
        if ($this->type !== 'finished') {
            return 0;
        }

        $variant = collect($this->size_variants)->firstWhere('size', $size);
        if (!$variant) {
            return 0;
        }

        $sellingPrice = $variant['price'];
        $costPrice = $this->calculateIngredientsCost($size);
        
        if ($costPrice == 0) {
            return 0;
        }

        return (($sellingPrice - $costPrice) / $sellingPrice) * 100;
    }

    /**
     * الحصول على هامش الربح بالريال
     */
    public function getProfitAmount($size = null)
    {
        if ($this->type !== 'finished') {
            return 0;
        }

        $variant = collect($this->size_variants)->firstWhere('size', $size);
        if (!$variant) {
            return 0;
        }

        $sellingPrice = $variant['price'];
        $costPrice = $this->calculateIngredientsCost($size);
        
        return $sellingPrice - $costPrice;
    }

}
