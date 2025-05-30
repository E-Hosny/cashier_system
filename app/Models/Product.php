<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;



class Product extends Model
{
    //
    use HasFactory;

    protected $fillable = ['name', 'price', 'quantity', 'image','tenant_id','category_id'];

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



}
