<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\ListCustomerDocuments;

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Documents\DTOs\DocumentSummary;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListCustomerDocumentsHandler implements RequestHandler
{
    /**
     * @param  ListCustomerDocuments  $request
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return Document::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->whereHas('customer', fn ($q) => $q->where('ulid', $request->customerId->toString()))
            ->where('status', DocumentStatus::Confirmed->value)
            ->when(
                $request->uploadSessionId,
                fn ($q, $sessionId) => $q->whereHas('uploadSession', fn ($s) => $s->where('ulid', $sessionId->toString())),
            )
            ->with(['uploadSession'])
            ->orderByDesc('uploaded_at')
            ->paginate($request->perPage)
            ->through(fn (Document $d) => DocumentSummary::fromModel($d));
    }
}
