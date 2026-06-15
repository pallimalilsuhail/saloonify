<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer;

use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * @see UpdateCustomerHandler
 *
 * @implements Request<Id>
 */
final readonly class UpdateCustomer implements Request, UsesDatabaseTransaction
{
    /**
     * Pass null to leave a field unchanged. Pass a value (or for email:
     * either an Email VO or false to clear) to update it.
     *
     * Email semantics: null = no change; an Email instance = set to that;
     * false = explicitly clear (set to null).
     */
    public function __construct(
        public Id $businessId,
        public Id $customerId,
        public ?string $name = null,
        public ?PhoneNumber $phone = null,
        public Email|false|null $email = null,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }

    public function hasBasicInfo(): bool
    {
        return $this->name !== null;
    }

    public function hasContactChanges(): bool
    {
        return $this->phone instanceof PhoneNumber || $this->email !== null;
    }

    public function clearsEmail(): bool
    {
        return $this->email === false;
    }

    public function setsEmail(): bool
    {
        return $this->email instanceof Email;
    }
}
