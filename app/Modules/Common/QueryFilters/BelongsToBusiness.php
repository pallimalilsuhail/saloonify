<?php

declare(strict_types=1);

namespace App\Modules\Common\QueryFilters;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Shared\ValueObjects\Id;

/**
 * Multi-tenancy enforcement: scope a query to records belonging to a business.
 *
 * Apply via ->tap(new BelongsToBusiness($businessId)) in every UseCase that
 * loads tenant data. Resolves the business by ULID via subquery so callers
 * never have to know the internal numeric id.
 */
final readonly class BelongsToBusiness
{
    public function __construct(
        private Id $businessId,
    ) {}

    public function __invoke(EloquentBuilder|QueryBuilder $query): void
    {
        $query->where('business_id', function ($query): void {
            $query->select('id')
                ->from('businesses_businesses')
                ->where('ulid', $this->businessId->toString())
                ->limit(1);
        });
    }
}
