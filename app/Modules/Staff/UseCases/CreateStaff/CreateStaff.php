<?php

declare(strict_types=1);

namespace App\Modules\Staff\UseCases\CreateStaff;

use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Staff\DTOs\StaffCreated;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;

/**
 * @see CreateStaffHandler
 *
 * @implements UsesDatabaseTransaction<StaffCreated>
 */
final readonly class CreateStaff implements UsesDatabaseTransaction
{
    /**
     * @param  array<int, int>  $locationIds
     */
    public function __construct(
        public int $businessId,
        public string $name,
        public ?string $email,
        public ?string $username,
        public string $password,
        public UserRole $role,
        public array $locationIds = [],
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
