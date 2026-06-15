<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\ListBusinesses;

use App\Modules\Businesses\DTOs\BusinessSummary;
use AvoqadoDev\UseCase\Contracts\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @see ListBusinessesHandler
 *
 * Returns a LengthAwarePaginator of {@see BusinessSummary}.
 *
 * @implements Request<LengthAwarePaginator>
 */
final readonly class ListBusinesses implements Request
{
    public function __construct(
        public int $perPage = 25,
        public ?string $search = null,
    ) {}
}
