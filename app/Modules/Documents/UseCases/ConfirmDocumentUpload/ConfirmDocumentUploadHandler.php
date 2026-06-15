<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\ConfirmDocumentUpload;

use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadSessionSubmitted;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\DTOs\ConfirmedUpload;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class ConfirmDocumentUploadHandler implements RequestHandler
{
    public function __construct(
        private CloudStorageService $cloudStorage,
    ) {}

    /**
     * @param  ConfirmDocumentUpload  $request
     */
    public function handle(Request $request): ConfirmedUpload
    {
        $session = UploadSession::query()
            ->where('token_hash', $request->token->hash())
            ->with(['business', 'customer'])
            ->first();

        if (! $session || ! $session->isActive()) {
            throw new UploadSessionNotAccepting('Upload link is not accepting submissions.');
        }

        $candidateUlids = array_map(fn (Id $id) => $id->toString(), $request->documentIds);

        $documents = Document::query()
            ->where('upload_session_id', $session->id)
            ->whereIn('ulid', $candidateUlids)
            ->get();

        $confirmed = [];
        $missing = [];

        foreach ($documents as $document) {
            if ($document->status->is(DocumentStatus::Confirmed)) {
                $confirmed[] = Id::fromString($document->ulid);

                continue;
            }

            if (! $this->cloudStorage->objectExists($document->s3_key)) {
                $missing[] = Id::fromString($document->ulid);

                continue;
            }

            $document->update([
                'status' => DocumentStatus::Confirmed->value,
                'uploaded_at' => now(),
            ]);

            $confirmed[] = Id::fromString($document->ulid);
        }

        // Document ids the client supplied that the server doesn't even
        // know about (wrong session / typo / never reached presign) are
        // treated as missing too — the page should retry the failed
        // PUT, which re-runs presign and gives a fresh document_id.
        $foundUlids = $documents->pluck('ulid')->all();
        foreach ($candidateUlids as $supplied) {
            if (! in_array($supplied, $foundUlids, true)) {
                $missing[] = Id::fromString($supplied);
            }
        }

        $sessionId = Id::fromString($session->ulid);
        $sessionFullyConfirmed = $missing === [] && count($confirmed) > 0;

        if ($sessionFullyConfirmed) {
            $session->update([
                'status' => UploadSessionStatus::Submitted->value,
                'submitted_at' => now(),
            ]);

            Event::dispatch(new UploadSessionSubmitted(
                sessionId: $sessionId,
                businessId: Id::fromString($session->business->ulid),
                customerId: Id::fromString($session->customer->ulid),
                documentIds: $confirmed,
            ));
        }

        return new ConfirmedUpload(
            sessionId: $sessionId,
            submitted: $sessionFullyConfirmed,
            confirmed: $confirmed,
            missing: $missing,
        );
    }
}
