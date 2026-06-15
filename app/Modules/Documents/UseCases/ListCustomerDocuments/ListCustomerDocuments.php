<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\ListCustomerDocuments;

use App\Modules\Documents\DTOs\DocumentSummary;
use AvoqadoDev\UseCase\Contracts\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Shared\ValueObjects\Id;

/**
 * @see ListCustomerDocumentsHandler
 *
 * Returns a paginator of {@see DocumentSummary}.
 *
 * @implements Request<LengthAwarePaginator>
 */
final readonly class ListCustomerDocuments implements Request
{
    public function __construct(
        public Id $businessId,
        public Id $customerId,
        public ?Id $uploadSessionId = null,
        public int $perPage = 25,
    ) {}
}
