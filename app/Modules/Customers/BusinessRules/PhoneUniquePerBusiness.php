<?php

declare(strict_types=1);

namespace App\Modules\Customers\BusinessRules;

use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

final readonly class PhoneUniquePerBusiness implements BusinessRule
{
    public function __construct(
        public Id $businessId,
        public PhoneNumber $phone,
        public ?Id $ignoreCustomerId = null,
    ) {}

    public function passes(): bool
    {
        return ! Customer::query()
            ->whereHas('business', fn ($q) => $q->where('ulid', $this->businessId->toString()))
            ->where('phone', $this->phone->toE164())
            ->when(
                $this->ignoreCustomerId instanceof Id,
                fn ($q) => $q->where('ulid', '!=', $this->ignoreCustomerId->toString()),
            )
            ->exists();
    }

    public function message(): string
    {
        return "A customer with phone {$this->phone->toE164()} already exists in this business.";
    }

    public function code(): string
    {
        return 'customer.phone.duplicate';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['phone' => $this->phone->toE164()];
    }
}
