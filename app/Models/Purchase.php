<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['tenant_id', 'supplier_name', 'description', 'quantity', 'total_amount', 'purchase_date'];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }
}

