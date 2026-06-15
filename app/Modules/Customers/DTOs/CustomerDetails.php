<?php

declare(strict_types=1);

namespace App\Modules\Customers\DTOs;

use App\Modules\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * Full customer detail for the show / edit surface, including the
 * creator's display name when present.
 *
 * Caller must eager-load the createdBy + business relations on the model
 * before mapping; otherwise the DTO will trigger lazy queries.
 */
final readonly class CustomerDetails
{
    public function __construct(
        public Id $id,
        public Id $businessId,
        public string $name,
        public PhoneNumber $phone,
        public ?Email $email,
        public ?Id $createdById,
        public ?string $createdByName,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(Customer $customer): self
    {
        $creator = $customer->createdBy;

        return new self(
            id: Id::fromString($customer->ulid),
            businessId: Id::fromString($customer->business->ulid),
            name: $customer->name,
            phone: PhoneNumber::fromE164($customer->phone),
            email: Email::fromNullable($customer->email),
            createdById: $creator !== null ? Id::fromString($creator->ulid) : null,
            createdByName: $creator?->name,
            createdAt: $customer->created_at?->toImmutable() ?? CarbonImmutable::now(),
            updatedAt: $customer->updated_at?->toImmutable() ?? CarbonImmutable::now(),
        );
    }
}
