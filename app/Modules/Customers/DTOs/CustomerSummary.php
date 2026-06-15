<?php

declare(strict_types=1);

namespace App\Modules\Customers\DTOs;

use App\Modules\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * Lightweight projection of a Customer for list views. No relations.
 */
final readonly class CustomerSummary
{
    public function __construct(
        public Id $id,
        public string $name,
        public PhoneNumber $phone,
        public ?Email $email,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(Customer $customer): self
    {
        return new self(
            id: Id::fromString($customer->ulid),
            name: $customer->name,
            phone: PhoneNumber::fromE164($customer->phone),
            email: Email::fromNullable($customer->email),
            createdAt: $customer->created_at?->toImmutable() ?? CarbonImmutable::now(),
        );
    }
}
