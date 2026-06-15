<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Events;

use App\Modules\Businesses\Enums\UserRole;
use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class MemberRoleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Id $memberId,
        public readonly ?Id $businessId,
        public readonly Id $changedById,
        public readonly UserRole $fromRole,
        public readonly UserRole $toRole,
    ) {}
}
