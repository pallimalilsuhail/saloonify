<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\RevokeInvite;

use App\Models\User;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Events\InvitationRevoked;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Invitation;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class RevokeInviteHandler implements RequestHandler
{
    /**
     * @param  RevokeInvite  $request
     */
    public function handle(Request $request): Id
    {
        $invitation = Invitation::query()
            ->where('ulid', $request->invitationId->toString())
            ->firstOrFail();

        $actor = User::query()
            ->where('ulid', $request->actorId->toString())
            ->firstOrFail();

        if (! $actor->isSuperAdmin() && ! ($actor->isOwner() && $actor->business_id === $invitation->business_id)) {
            throw InvitationException::notAuthorisedForBusiness();
        }

        $invitation->update([
            'status' => InvitationStatus::Revoked->value,
            'revoked_at' => now(),
        ]);

        $businessUlid = Business::query()->whereKey($invitation->business_id)->value('ulid');

        Event::dispatch(new InvitationRevoked(
            invitationId: $request->invitationId,
            businessId: Id::fromString($businessUlid),
            revokedById: $request->actorId,
        ));

        return $request->invitationId;
    }
}
