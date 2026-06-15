<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Events;

use App\Modules\Businesses\Enums\UserRole;
use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class InvitationIssued
{
    use Dispatchable;

    public function __construct(
        public readonly Id $invitationId,
        public readonly Id $businessId,
        public readonly Id $invitedById,
        public readonly string $email,
        public readonly UserRole $role,
    ) {}
}
