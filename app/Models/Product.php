<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToTenant;
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
        'quantity_per_unit',
        'unit_consume_price',
        'barcode',
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

    public function rawMaterialPendingLabels()
    {
        return $this->hasMany(RawMaterialPendingLabel::class, 'product_id');
    }

    protected static function booted()
    {
        static::bootBelongsToTenant();
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
            'extra_large' => 'كان كبير',
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
        if ($this->type !== 'raw' || ! $this->unit_consume_price) {
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
        if (! $variant) {
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
        if (! $variant) {
            return 0;
        }

        $sellingPrice = $variant['price'];
        $costPrice = $this->calculateIngredientsCost($size);

        return $sellingPrice - $costPrice;
    }
}
