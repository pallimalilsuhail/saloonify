<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Http\Requests\DownloadDocumentRequest;
use App\Modules\Documents\UseCases\DownloadDocument\DownloadDocument;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\RedirectResponse;

final class DownloadDocumentController
{
    public function __invoke(DownloadDocumentRequest $request): RedirectResponse
    {
        try {
            $result = Mediator::dispatch(new DownloadDocument(
                businessId: $request->getBusinessId(),
                documentId: $request->asRouteId('document'),
                actorId: $request->getActorId(),
            ));
        } catch (DocumentAccessDenied) {
            abort(403);
        }

        return redirect()->away($result->url);
    }
}
