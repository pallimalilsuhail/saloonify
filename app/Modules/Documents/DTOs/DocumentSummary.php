<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTOs;

use App\Modules\Documents\Models\Document;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Lightweight projection of a confirmed Document for the customer
 * detail page list. No s3_key — that's only useful for the view URL
 * generator, never the list.
 */
final readonly class DocumentSummary
{
    public function __construct(
        public Id $id,
        public Id $uploadSessionId,
        public string $originalName,
        public string $mime,
        public int $sizeBytes,
        public ?CarbonImmutable $uploadedAt,
    ) {}

    public static function fromModel(Document $document): self
    {
        $session = $document->uploadSession;

        return new self(
            id: Id::fromString($document->ulid),
            uploadSessionId: Id::fromString($session->ulid),
            originalName: $document->original_name,
            mime: $document->mime,
            sizeBytes: $document->size_bytes,
            uploadedAt: $document->uploaded_at?->toImmutable(),
        );
    }
}
