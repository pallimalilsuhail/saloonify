<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\AddLocation;

use App\Modules\Businesses\DTOs\LocationCreated;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\OpeningHours;

/**
 * @see AddLocationHandler
 *
 * @implements Request<LocationCreated>
 */
final readonly class AddLocation implements Request
{
    public function __construct(
        public Id $businessId,
        public string $name,
        public Address $address,
        public OpeningHours $openingHours,
    ) {}
}
