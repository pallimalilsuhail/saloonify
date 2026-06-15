<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\UpdateMemberRole;

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Events\MemberRoleChanged;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class UpdateMemberRoleHandler implements RequestHandler
{
    /**
     * @param  UpdateMemberRole  $request
     */
    public function handle(Request $request): Id
    {
        $member = User::query()
            ->where('ulid', $request->memberId->toString())
            ->firstOrFail();

        $actor = User::query()
            ->where('ulid', $request->actorId->toString())
            ->firstOrFail();

        if ($actor->id === $member->id) {
            throw InvitationException::cannotChangeOwnRole();
        }

        if ($request->newRole->is(UserRole::SuperAdmin)) {
            throw InvitationException::notAuthorisedToInvite();
        }

        if (! $actor->isSuperAdmin()) {
            if (! $actor->isOwner() || $actor->business_id !== $member->business_id) {
                throw InvitationException::notAuthorisedForBusiness();
            }
        }

        $fromRole = $member->role;
        $member->update(['role' => $request->newRole->value]);

        $businessUlid = $member->business_id !== null
            ? Business::query()->whereKey($member->business_id)->value('ulid')
            : null;

        Event::dispatch(new MemberRoleChanged(
            memberId: $request->memberId,
            businessId: $businessUlid !== null ? Id::fromString($businessUlid) : null,
            changedById: $request->actorId,
            fromRole: $fromRole,
            toRole: $request->newRole,
        ));

        return $request->memberId;
    }
}
