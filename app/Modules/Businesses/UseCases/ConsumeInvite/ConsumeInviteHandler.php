<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\ConsumeInvite;

use App\Modules\Businesses\DTOs\AcceptedInvite;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Events\InvitationConsumed;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Invitation;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class ConsumeInviteHandler implements RequestHandler, UsesDatabaseTransaction
{
    public function transactionAttempts(): int
    {
        return 1;
    }

    /**
     * @param  ConsumeInvite  $request
     */
    public function handle(Request $request): AcceptedInvite
    {
        $invitation = Invitation::query()
            ->where('token_hash', $request->token->hash())
            ->first();

        if (! $invitation) {
            throw InvitationException::invalidToken();
        }

        if ($invitation->status !== InvitationStatus::Pending) {
            throw InvitationException::alreadyConsumed();
        }

        if ($invitation->isExpired()) {
            $invitation->update(['status' => InvitationStatus::Expired->value]);

            throw InvitationException::invalidToken();
        }

        if (strtolower($invitation->email) !== strtolower($request->user->email)) {
            throw InvitationException::emailMismatch();
        }

        $request->user->update([
            'business_id' => $invitation->business_id,
            'role' => $invitation->role->value,
        ]);

        $invitation->update([
            'status' => InvitationStatus::Accepted->value,
            'accepted_at' => now(),
        ]);

        $businessUlid = Business::query()->whereKey($invitation->business_id)->value('ulid');

        Event::dispatch(new InvitationConsumed(
            invitationId: Id::fromString($invitation->ulid),
            businessId: Id::fromString($businessUlid),
            userId: Id::fromString($request->user->ulid),
        ));

        return new AcceptedInvite(
            invitationId: Id::fromString($invitation->ulid),
            userId: Id::fromString($request->user->ulid),
            businessId: Id::fromString($businessUlid),
        );
    }
}
