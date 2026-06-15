<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases\ListCustomers;

use App\Modules\Customers\DTOs\CustomerSummary;
use AvoqadoDev\UseCase\Contracts\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\ValueObjects\Id;

/**
 * @see ListCustomersHandler
 *
 * Returns a LengthAwarePaginator of {@see CustomerSummary}.
 *
 * @implements Request<LengthAwarePaginator>
 */
final readonly class ListCustomers implements Request
{
    public function __construct(
        public Id $businessId,
        public ?string $search = null,
        public int $perPage = 25,
    ) {}
}
