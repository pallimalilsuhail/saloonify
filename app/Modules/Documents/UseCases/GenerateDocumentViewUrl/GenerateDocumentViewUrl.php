<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\GenerateDocumentViewUrl;

use App\Modules\Documents\DTOs\DocumentViewUrl;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see GenerateDocumentViewUrlHandler
 *
 * @implements Request<DocumentViewUrl>
 */
final readonly class GenerateDocumentViewUrl implements Request
{
    public function __construct(
        public Id $businessId,
        public Id $documentId,
        public Id $actorId,
        public int $expiryMinutes = 60,
    ) {}
}
