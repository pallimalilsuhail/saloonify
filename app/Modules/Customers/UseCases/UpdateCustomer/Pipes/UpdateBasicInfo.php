<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\UpdateCustomer\Pipes;

use App\Modules\Customers\UseCases\UpdateCustomer\UpdateCustomerPassable;
use Closure;

final readonly class UpdateBasicInfo
{
    public function handle(UpdateCustomerPassable $passable, Closure $next): mixed
    {
        if (! $passable->request->hasBasicInfo()) {
            return $next($passable);
        }

        $oldName = $passable->customer->name;
        $newName = $passable->request->name;

        if ($oldName === $newName) {
            return $next($passable);
        }

        $passable->customer->update(['name' => $newName]);
        $passable->recordChange('name', $oldName, $newName);

        return $next($passable);
    }
}
