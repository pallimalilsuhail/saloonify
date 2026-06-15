<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\RevokeUploadLink;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see RevokeUploadLinkHandler
 *
 * @implements Request<Id>
 */
final readonly class RevokeUploadLink implements Request
{
    public function __construct(
        public Id $sessionId,
        public ?Id $revokedById = null,
    ) {}
}
