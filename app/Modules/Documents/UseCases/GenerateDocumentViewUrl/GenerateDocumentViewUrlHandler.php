<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\GenerateDocumentViewUrl;

use App\Models\User;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Documents\DTOs\DocumentViewUrl;
use App\Modules\Documents\Events\DocumentViewUrlIssued;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class GenerateDocumentViewUrlHandler implements RequestHandler
{
    public function __construct(
        private CloudStorageService $cloudStorage,
    ) {}

    /**
     * @param  GenerateDocumentViewUrl  $request
     */
    public function handle(Request $request): DocumentViewUrl
    {
        $document = Document::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->documentId->toString())
            ->firstOrFail();

        $this->authoriseActor($request->actorId, $document->business_id);

        $url = $this->cloudStorage->generatePresignedDownloadUrl(
            $document->s3_key,
            $request->expiryMinutes,
        );

        Event::dispatch(new DocumentViewUrlIssued(
            documentId: Id::fromString($document->ulid),
            businessId: $request->businessId,
            actorId: $request->actorId,
        ));

        return new DocumentViewUrl(
            documentId: Id::fromString($document->ulid),
            url: $url,
            expiresAt: CarbonImmutable::now()->addMinutes($request->expiryMinutes),
        );
    }

    private function authoriseActor(Id $actorId, int $documentBusinessId): void
    {
        $actor = User::query()
            ->where('ulid', $actorId->toString())
            ->first();

        if (! $actor) {
            throw DocumentAccessDenied::notInBusiness();
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->business_id !== $documentBusinessId) {
            throw DocumentAccessDenied::notInBusiness();
        }
    }
}
