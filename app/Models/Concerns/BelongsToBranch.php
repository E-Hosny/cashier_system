<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            $branchId = BranchContext::id();
            if ($branchId === null) {
                return;
            }
            $table = $query->getModel()->getTable();
            if (! Schema::hasColumn($table, 'branch_id')) {
                return;
            }
            $query->where($table.'.branch_id', $branchId);
        });

        static::creating(function (Model $model) {
            $column = static::branchColumnName();
            if (! Schema::hasColumn($model->getTable(), $column)) {
                return;
            }
            $branchId = BranchContext::id();
            if ($branchId === null) {
                return;
            }
            $model->{$column} = $model->{$column} ?? $branchId;
        });
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class, static::branchColumnName());
    }

    protected static function branchColumnName(): string
    {
        return 'branch_id';
    }
}
