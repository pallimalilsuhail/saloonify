<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

/**
 * Result of onboarding a business — no models leak out of the use case.
 */
final readonly class OnboardedBusiness
{
    public function __construct(
        public string $businessId,
        public string $login,
    ) {}
}
