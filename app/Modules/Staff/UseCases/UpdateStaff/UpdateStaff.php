<?php

declare(strict_types=1);

namespace App\Modules\Staff\UseCases\UpdateStaff;

use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Staff\DTOs\StaffUpdated;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;

/**
 * @see UpdateStaffHandler
 *
 * @implements UsesDatabaseTransaction<StaffUpdated>
 */
final readonly class UpdateStaff implements UsesDatabaseTransaction
{
    /**
     * @param  array<int, int>|null  $locationIds
     */
    public function __construct(
        public int $userId,
        public ?string $name = null,
        public ?UserRole $role = null,
        public ?UserStatus $status = null,
        public ?array $locationIds = null,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
