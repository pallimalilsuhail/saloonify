<?php

declare(strict_types=1);

namespace App\Modules\Documents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

/**
 * Fired every time a presigned view URL is issued. The audit listener
 * (#32) records who viewed which document at what time — even though
 * we never log the raw S3 key or signed URL.
 */
final class DocumentViewUrlIssued
{
    use Dispatchable;

    public function __construct(
        public readonly Id $documentId,
        public readonly Id $businessId,
        public readonly Id $actorId,
    ) {}
}
