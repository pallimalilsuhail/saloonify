<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\ListCustomers;

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\DTOs\CustomerSummary;
use App\Modules\Customers\Models\Customer;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCustomersHandler implements RequestHandler
{
    /**
     * @param  ListCustomers  $request
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return Customer::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->when(
                $request->search,
                fn ($q, string $term) => $q->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                }),
            )
            ->orderByDesc('created_at')
            ->paginate($request->perPage)
            ->through(fn (Customer $customer) => CustomerSummary::fromModel($customer));
    }
}
