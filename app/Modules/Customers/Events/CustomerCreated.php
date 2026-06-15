<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Shared\ValueObjects\Id;

final class CustomerCreated
{
    use Dispatchable;

    public function __construct(
        public readonly Id $customerId,
        public readonly Id $businessId,
        public readonly ?Id $createdById,
    ) {}
}
