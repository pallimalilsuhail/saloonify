<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class MemberRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly Id $memberId,
        public readonly Id $businessId,
        public readonly Id $removedById,
    ) {}
}
