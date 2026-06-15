<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\ConsumeInvite;

use App\Models\User;
use App\Modules\Businesses\DTOs\AcceptedInvite;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Token;

/**
 * @see ConsumeInviteHandler
 *
 * @implements Request<AcceptedInvite>
 */
final readonly class ConsumeInvite implements Request
{
    public function __construct(
        public Token $token,
        public User $user,
    ) {}
}
