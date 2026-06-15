<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\DeleteDocument;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see DeleteDocumentHandler
 *
 * @implements Request<Id>
 */
final readonly class DeleteDocument implements Request
{
    public function __construct(
        public Id $businessId,
        public Id $documentId,
        public Id $actorId,
    ) {}
}
