<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

use Shared\ValueObjects\Id;

final readonly class IssuedInvitation
{
    public function __construct(
        public Id $invitationId,
        public string $rawToken,
        public string $url,
    ) {}
}
