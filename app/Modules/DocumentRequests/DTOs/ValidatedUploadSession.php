<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\DTOs;

use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Public-side view of an upload session. Carries only the minimum the
 * uploader page needs — business name, limits, expiry, current status.
 *
 * Customer name is intentionally NOT included: the link could be
 * forwarded, and the customer's identity is sensitive PII.
 */
final readonly class ValidatedUploadSession
{
    public function __construct(
        public Id $sessionId,
        public string $businessName,
        public UploadSessionStatus $status,
        public CarbonImmutable $expiresAt,
        public int $maxFiles,
        public int $maxBytes,
        /** @var array<int, string> */
        public array $allowedMime,
    ) {}

    public function isActive(): bool
    {
        return $this->status->is(UploadSessionStatus::Active) && $this->expiresAt->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status->is(UploadSessionStatus::Expired) || $this->expiresAt->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->status->is(UploadSessionStatus::Submitted);
    }

    public function isRevoked(): bool
    {
        return $this->status->is(UploadSessionStatus::Revoked);
    }
}
