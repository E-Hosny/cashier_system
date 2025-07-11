<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'total',
        'status',
        'payment_method',
        'tenant_id',
        'offline_id'
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
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



}

