<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCount extends Model
{
    use BelongsToTenant;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'status',
        'started_by',
        'completed_by',
        'started_at',
        'completed_at',
        'notes',
        'items_count',
        'counted_items_count',
        'total_surplus_qty',
        'total_shortage_qty',
        'total_surplus_value',
        'total_shortage_value',
        'net_diff_value',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'items_count' => 'integer',
            'counted_items_count' => 'integer',
            'total_surplus_qty' => 'decimal:4',
            'total_shortage_qty' => 'decimal:4',
            'total_surplus_value' => 'decimal:2',
            'total_shortage_value' => 'decimal:2',
            'net_diff_value' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::bootBelongsToTenant();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCountItem::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
