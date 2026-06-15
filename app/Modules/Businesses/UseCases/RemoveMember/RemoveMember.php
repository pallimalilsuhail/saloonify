<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\RemoveMember;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see RemoveMemberHandler
 *
 * @implements Request<Id>
 */
final readonly class RemoveMember implements Request
{
    public function __construct(
        public Id $memberId,
        public Id $actorId,
    ) {}
}
