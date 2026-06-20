<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

use JsonSerializable;
use Shared\ValueObjects\Id;

final readonly class LocationCreated implements JsonSerializable
{
    public function __construct(
        public Id $locationId,
    ) {}

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return ['location_id' => $this->locationId->toString()];
    }
}
