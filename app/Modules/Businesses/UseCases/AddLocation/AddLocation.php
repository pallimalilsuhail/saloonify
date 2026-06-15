<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\AddLocation;

use App\Modules\Businesses\DTOs\LocationCreated;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\OpeningHours;

/**
 * @see AddLocationHandler
 *
 * @implements UsesDatabaseTransaction<LocationCreated>
 */
final readonly class AddLocation implements UsesDatabaseTransaction
{
    public function __construct(
        public string $businessUlid,
        public string $name,
        public Address $address,
        public OpeningHours $openingHours,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
