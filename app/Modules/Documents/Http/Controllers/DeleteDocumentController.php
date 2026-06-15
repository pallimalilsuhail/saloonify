<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Http\Requests\DeleteDocumentRequest;
use App\Modules\Documents\UseCases\DeleteDocument\DeleteDocument;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\RedirectResponse;

final class DeleteDocumentController
{
    public function __invoke(DeleteDocumentRequest $request): RedirectResponse
    {
        try {
            Mediator::dispatch(new DeleteDocument(
                businessId: $request->getBusinessId(),
                documentId: $request->asRouteId('document'),
                actorId: $request->getActorId(),
            ));
        } catch (DocumentAccessDenied) {
            abort(403);
        }

        return back()->with('status', 'document.deleted');
    }
}
