<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\UpdateMemberRole;

use App\Modules\Businesses\Enums\UserRole;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see UpdateMemberRoleHandler
 *
 * @implements Request<Id>
 */
final readonly class UpdateMemberRole implements Request
{
    public function __construct(
        public Id $memberId,
        public UserRole $newRole,
        public Id $actorId,
    ) {}
}
