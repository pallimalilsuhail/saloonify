<?php

declare(strict_types=1);

namespace Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that filters every query on a tenant model to the
 * business bound in the container (`tenant.business_id`). When no tenant
 * is bound (e.g. super-admin / console), no filter is applied.
 */
final class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenant.business_id')) {
            return;
        }

        $businessId = app('tenant.business_id');

        if ($businessId === null) {
            return;
        }

        $builder->where($model->qualifyColumn('business_id'), $businessId);
    }
}
