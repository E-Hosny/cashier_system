<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $column = $query->getModel()->getTable() . '.' . static::tenantColumnName();
                $query->where($column, auth()->user()->tenant_id);
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->tenant_id) {
                $column = static::tenantColumnName();
                if (\Schema::hasColumn($model->getTable(), $column)) {
                    $model->{$column} = $model->{$column} ?? auth()->user()->tenant_id;
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, static::tenantColumnName());
    }

    protected static function tenantColumnName(): string
    {
        return 'tenant_id';
    }
}
