<?php

declare(strict_types=1);

namespace App\Modules\Customers\BusinessRules;

use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

final readonly class EmailUniquePerBusiness implements BusinessRule
{
    public function __construct(
        public Id $businessId,
        public Email $email,
        public ?Id $ignoreCustomerId = null,
    ) {}

    public function passes(): bool
    {
        return ! Customer::query()
            ->whereHas('business', fn ($q) => $q->where('ulid', $this->businessId->toString()))
            ->where('email', $this->email->toString())
            ->when(
                $this->ignoreCustomerId instanceof Id,
                fn ($q) => $q->where('ulid', '!=', $this->ignoreCustomerId->toString()),
            )
            ->exists();
    }

    public function message(): string
    {
        return "A customer with email {$this->email->toString()} already exists in this business.";
    }

    public function code(): string
    {
        return 'customer.email.duplicate';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['email' => $this->email->toString()];
    }
}
