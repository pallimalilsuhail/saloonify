<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\DeleteDocument;

use App\Models\User;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Documents\Events\DocumentDeleted;
use App\Modules\Documents\Exceptions\DocumentAccessDenied;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

/**
 * Soft-deletes a document and tags the underlying S3 object so a bucket
 * lifecycle policy can purge it after the configured grace period.
 * Owner-only — members cannot delete.
 */
final readonly class DeleteDocumentHandler implements RequestHandler
{
    public function __construct(
        private CloudStorageService $cloudStorage,
    ) {}

    /**
     * @param  DeleteDocument  $request
     */
    public function handle(Request $request): Id
    {
        $document = Document::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->documentId->toString())
            ->firstOrFail();

        $this->authoriseActor($request->actorId, $document->business_id);

        $this->cloudStorage->tagObject($document->s3_key, [
            'pending-delete' => 'true',
        ]);

        $document->delete();

        Event::dispatch(new DocumentDeleted(
            documentId: Id::fromString($document->ulid),
            businessId: $request->businessId,
            actorId: $request->actorId,
        ));

        return $request->documentId;
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

        if (! $actor->isOwner() || $actor->business_id !== $documentBusinessId) {
            throw DocumentAccessDenied::notInBusiness();
        }
    }
}
