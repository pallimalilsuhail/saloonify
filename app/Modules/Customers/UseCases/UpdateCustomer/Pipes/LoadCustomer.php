<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer\Pipes;

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomerPassable;
use Closure;

final readonly class LoadCustomer
{
    public function handle(UpdateCustomerPassable $passable, Closure $next): mixed
    {
        $passable->customer = Customer::query()
            ->tap(new BelongsToBusiness($passable->request->businessId))
            ->where('ulid', $passable->request->customerId->toString())
            ->firstOrFail();

        return $next($passable);
    }
}
