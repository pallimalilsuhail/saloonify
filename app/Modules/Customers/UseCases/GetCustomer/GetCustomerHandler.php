<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\GetCustomer;

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\DTOs\CustomerDetails;
use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final readonly class GetCustomerHandler implements RequestHandler
{
    /**
     * @param  GetCustomer  $request
     */
    public function handle(Request $request): CustomerDetails
    {
        $customer = Customer::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->customerId->toString())
            ->with(['createdBy', 'business'])
            ->firstOrFail();

        return CustomerDetails::fromModel($customer);
    }
}
