<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\InviteMember;

use App\Modules\Businesses\DTOs\IssuedInvitation;
use App\Modules\Businesses\Enums\UserRole;
use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

/**
 * @see InviteMemberHandler
 *
 * @implements Request<IssuedInvitation>
 */
final readonly class InviteMember implements Request
{
    public function __construct(
        public Id $businessId,
        public Email $email,
        public UserRole $role,
        public Id $invitedById,
    ) {}
}
