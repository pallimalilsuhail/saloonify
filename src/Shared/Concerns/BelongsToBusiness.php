<?php

declare(strict_types=1);

namespace Shared\Concerns;

use Shared\Scopes\BusinessScope;

/**
 * Apply to every business-owned model. Adds the BusinessScope global
 * scope (auto-filters reads to the bound tenant) and auto-fills
 * `business_id` on create from `tenant.business_id` when not set.
 *
 * Super-admin / console (no tenant bound) bypasses the scope and must
 * set `business_id` explicitly.
 */
trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope);

        static::creating(function ($model): void {
            if (! empty($model->business_id)) {
                return;
            }

            if (app()->bound('tenant.business_id') && app('tenant.business_id') !== null) {
                $model->business_id = app('tenant.business_id');
            }
        });
    }
}
