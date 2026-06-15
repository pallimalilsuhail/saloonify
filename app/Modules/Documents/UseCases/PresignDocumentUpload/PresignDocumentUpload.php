<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\PresignDocumentUpload;

use App\Modules\Documents\DTOs\PresignedDocumentUpload as PresignedDocumentUploadDto;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Token;

/**
 * @see PresignDocumentUploadHandler
 *
 * @implements Request<PresignedDocumentUploadDto>
 */
final readonly class PresignDocumentUpload implements Request
{
    public function __construct(
        public Token $token,
        public string $filename,
        public string $mime,
        public int $sizeBytes,
    ) {}
}
