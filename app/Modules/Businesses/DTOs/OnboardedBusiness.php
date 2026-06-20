<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

use JsonSerializable;
use Shared\ValueObjects\Id;

/**
 * Result of onboarding a business — no models leak out of the use case.
 */
final readonly class OnboardedBusiness implements JsonSerializable
{
    public function __construct(
        public Id $businessId,
        public string $login,
    ) {}

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return ['business_id' => $this->businessId->toString(), 'login' => $this->login];
    }
}
