<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\ValidateToken;

use App\Modules\DocumentRequests\DTOs\ValidatedUploadSession;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Exceptions\InvalidUploadToken;
use App\Modules\DocumentRequests\Models\UploadSession;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

final readonly class ValidateTokenHandler implements RequestHandler
{
    /**
     * @param  ValidateToken  $request
     */
    public function handle(Request $request): ValidatedUploadSession
    {
        $session = UploadSession::query()
            ->where('token_hash', $request->token->hash())
            ->with(['business'])
            ->first();

        if (! $session) {
            throw InvalidUploadToken::notFound();
        }

        $status = $session->status;
        if ($status->is(UploadSessionStatus::Active) && $session->isExpired()) {
            $status = UploadSessionStatus::Expired;
        }

        return new ValidatedUploadSession(
            sessionId: Id::fromString($session->ulid),
            businessName: $session->business->name,
            status: $status,
            expiresAt: CarbonImmutable::parse($session->expires_at),
            maxFiles: $session->max_files,
            maxBytes: $session->max_bytes,
            allowedMime: $session->allowed_mime,
        );
    }
}
