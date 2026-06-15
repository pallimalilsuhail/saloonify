<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\GetCustomer;

use App\Modules\Customers\DTOs\CustomerDetails;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see GetCustomerHandler
 *
 * @implements Request<CustomerDetails>
 */
final readonly class GetCustomer implements Request
{
    public function __construct(
        public Id $businessId,
        public Id $customerId,
    ) {}
}
