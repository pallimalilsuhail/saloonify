<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\RegenerateUploadLink;

use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;
use Shared\ValueObjects\Id;

/**
 * @see RegenerateUploadLinkHandler
 *
 * @implements Request<IssuedUploadLink>
 */
final readonly class RegenerateUploadLink implements Request, UsesDatabaseTransaction
{
    public function __construct(
        public Id $businessId,
        public Id $customerId,
        public ?Id $generatedById = null,
        public ?int $expiryMinutes = null,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
