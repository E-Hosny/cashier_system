<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'name',
        'max_present',
        'tenant_id',
    ];

    protected $casts = [
        'max_present' => 'integer',
    ];

    protected static function booted()
    {
        static::bootBelongsToTenant();
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'attendance_group_id');
    }
}

