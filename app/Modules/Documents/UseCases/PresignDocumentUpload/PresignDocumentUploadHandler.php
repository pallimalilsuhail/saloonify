<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\PresignDocumentUpload;

use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\BusinessRules\FileCountWithinLimit;
use App\Modules\Documents\BusinessRules\FileSizeWithinLimit;
use App\Modules\Documents\BusinessRules\MimeAllowed;
use App\Modules\Documents\DTOs\PresignedDocumentUpload as PresignedDocumentUploadDto;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Models\Document;
use App\Services\CloudStorage\CloudStorageService;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

final readonly class PresignDocumentUploadHandler implements RequestHandler
{
    public function __construct(
        private CloudStorageService $cloudStorage,
        private GuardsRules $guards,
    ) {}

    /**
     * @param  PresignDocumentUpload  $request
     */
    public function handle(Request $request): PresignedDocumentUploadDto
    {
        $session = UploadSession::query()
            ->where('token_hash', $request->token->hash())
            ->with(['business', 'customer'])
            ->first();

        if (! $session || ! $session->isActive()) {
            throw new UploadSessionNotAccepting('Upload link is not accepting files.');
        }

        $this->guards->guard(
            new MimeAllowed($request->mime, $session->allowed_mime),
            new FileSizeWithinLimit($request->sizeBytes, $session->max_bytes),
            new FileCountWithinLimit($session->id, $session->max_files),
        );

        $folder = sprintf(
            'business/%s/customer/%s/session/%s',
            $session->business->ulid,
            $session->customer->ulid,
            $session->ulid,
        );

        $result = $this->cloudStorage->generatePresignedUploadUrl(
            folder: $folder,
            filename: $request->filename,
            mimeType: $request->mime,
            maxSizeBytes: $request->sizeBytes,
        );

        $document = Document::create([
            'business_id' => $session->business_id,
            'customer_id' => $session->customer_id,
            'upload_session_id' => $session->id,
            's3_key' => $result->filePath,
            'original_name' => $request->filename,
            'mime' => $request->mime,
            'size_bytes' => $request->sizeBytes,
            'status' => DocumentStatus::Pending->value,
        ]);

        return new PresignedDocumentUploadDto(
            documentId: Id::fromString($document->ulid),
            uploadUrl: $result->uploadUrl,
            s3Key: $result->filePath,
            expiresAt: CarbonImmutable::parse($result->expiresAt),
        );
    }
}
