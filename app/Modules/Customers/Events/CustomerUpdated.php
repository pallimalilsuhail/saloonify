<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class CustomerUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function __construct(
        public readonly Id $customerId,
        public readonly Id $businessId,
        public readonly array $changes,
    ) {}
}
