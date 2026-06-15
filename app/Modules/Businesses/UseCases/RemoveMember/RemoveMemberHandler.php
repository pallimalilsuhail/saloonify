<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\RemoveMember;

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Events\MemberRemoved;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class RemoveMemberHandler implements RequestHandler
{
    /**
     * @param  RemoveMember  $request
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

        if (! $actor->isSuperAdmin() && ! ($actor->isOwner() && $actor->business_id === $member->business_id)) {
            throw InvitationException::notAuthorisedForBusiness();
        }

        if (! $member->business_id) {
            throw InvitationException::memberNotInBusiness();
        }

        $previousBusinessId = $member->business_id;

        $member->update([
            'business_id' => null,
            'role' => UserRole::Member->value,
        ]);

        $businessUlid = Business::query()->whereKey($previousBusinessId)->value('ulid');

        Event::dispatch(new MemberRemoved(
            memberId: $request->memberId,
            businessId: Id::fromString($businessUlid),
            removedById: $request->actorId,
        ));

        return $request->memberId;
    }
}
