<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\OnboardBusiness;

use App\Modules\Businesses\DTOs\OnboardedBusiness;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;

/**
 * @see OnboardBusinessHandler
 *
 * @implements UsesDatabaseTransaction<OnboardedBusiness>
 */
final readonly class OnboardBusiness implements UsesDatabaseTransaction
{
    public function __construct(
        public string $name,
        public string $trn,
        public string $adminName,
        public string $login,
        public string $password,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
