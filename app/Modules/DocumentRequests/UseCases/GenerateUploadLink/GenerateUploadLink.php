<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\GenerateUploadLink;

use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see GenerateUploadLinkHandler
 *
 * @implements Request<IssuedUploadLink>
 */
final readonly class GenerateUploadLink implements Request
{
    public function __construct(
        public Id $businessId,
        public Id $customerId,
        public ?Id $generatedById = null,
        public ?int $expiryMinutes = null,
    ) {}
}
