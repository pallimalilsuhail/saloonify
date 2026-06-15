<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\ValidateToken;

use App\Modules\DocumentRequests\DTOs\ValidatedUploadSession;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Token;

/**
 * @see ValidateTokenHandler
 *
 * @implements Request<ValidatedUploadSession>
 */
final readonly class ValidateToken implements Request
{
    public function __construct(
        public Token $token,
    ) {}
}
