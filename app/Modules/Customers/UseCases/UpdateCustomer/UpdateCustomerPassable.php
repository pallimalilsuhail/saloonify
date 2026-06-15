<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer;

use App\Modules\Common\Services\EventCollector;
use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;

final class UpdateCustomerPassable
{
    /** @var array<string, array{old: mixed, new: mixed}> */
    public array $changes = [];

    public function __construct(
        public readonly UpdateCustomer $request,
        public ?Customer $customer,
        public readonly EventCollector $eventCollector,
        public readonly GuardsRules $guardsRules,
    ) {}

    public function recordChange(string $field, mixed $old, mixed $new): void
    {
        if ($old !== $new) {
            $this->changes[$field] = ['old' => $old, 'new' => $new];
        }
    }
}
