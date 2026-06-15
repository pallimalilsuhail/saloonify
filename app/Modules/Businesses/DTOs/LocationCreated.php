<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

final readonly class LocationCreated
{
    public function __construct(
        public string $locationId,
    ) {}
}
