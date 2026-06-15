<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

use Shared\ValueObjects\Id;

/**
 * Result of consuming an invite. Carries the two ids the auth
 * flow needs to log the user in and (later) record the audit event.
 * The handler does not leak the User model — callers fetch it themselves
 * if they need the auth-shaped instance.
 */
final readonly class AcceptedInvite
{
    public function __construct(
        public Id $invitationId,
        public Id $userId,
        public Id $businessId,
    ) {}
}
