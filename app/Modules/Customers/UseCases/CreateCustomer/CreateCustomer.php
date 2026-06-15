<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\CreateCustomer;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * @see CreateCustomerHandler
 *
 * @implements Request<Id>
 */
final readonly class CreateCustomer implements Request
{
    public function __construct(
        public Id $businessId,
        public string $name,
        public PhoneNumber $phone,
        public ?Email $email = null,
        public ?Id $createdById = null,
    ) {}
}
