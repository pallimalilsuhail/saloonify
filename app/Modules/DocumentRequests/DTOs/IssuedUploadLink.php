<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\DTOs;

use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Returned ONCE when an upload link is generated. The raw token is held
 * in this DTO and never persisted in the clear — the agent passes the
 * url to the customer out-of-band, then it's only retrievable via the
 * customer presenting it.
 */
final readonly class IssuedUploadLink
{
    public function __construct(
        public Id $sessionId,
        public string $rawToken,
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}
}
