<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTOs;

use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Result of a presign call. Browser uses uploadUrl to PUT the file
 * directly to S3, then calls /confirm with the documentId once the
 * upload completes.
 */
final readonly class PresignedDocumentUpload
{
    public function __construct(
        public Id $documentId,
        public string $uploadUrl,
        public string $s3Key,
        public CarbonImmutable $expiresAt,
        public string $method = 'PUT',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'document_id' => $this->documentId->toString(),
            'upload_url' => $this->uploadUrl,
            's3_key' => $this->s3Key,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'method' => $this->method,
        ];
    }
}
