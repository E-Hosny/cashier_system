<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'description',
        'amount',
        'expense_date',
        'tenant_id',
        'branch_id',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
} 