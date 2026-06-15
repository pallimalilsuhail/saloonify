<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\RevokeInvite;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see RevokeInviteHandler
 *
 * @implements Request<Id>
 */
final readonly class RevokeInvite implements Request
{
    public function __construct(
        public Id $invitationId,
        public Id $actorId,
    ) {}
}
