<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Http\Requests\ViewDocumentRequest;
use App\Modules\Documents\UseCases\GenerateDocumentViewUrl\GenerateDocumentViewUrl;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\RedirectResponse;

final class ViewDocumentController
{
    public function __invoke(ViewDocumentRequest $request): RedirectResponse
    {
        try {
            $result = Mediator::dispatch(new GenerateDocumentViewUrl(
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
