<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTOs;

use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Time-limited presigned URL pointing at the underlying S3 object.
 * Returned to the agent's UI so the browser can fetch the document
 * without the server proxying the bytes.
 *
 * The raw s3_key never leaves the handler — only the signed URL does.
 */
final readonly class DocumentViewUrl
{
    public function __construct(
        public Id $documentId,
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}
}
