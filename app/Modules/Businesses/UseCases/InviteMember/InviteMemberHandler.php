<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\InviteMember;

use App\Models\User;
use App\Modules\Businesses\DTOs\IssuedInvitation;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Events\InvitationIssued;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Invitation;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

final readonly class InviteMemberHandler implements RequestHandler
{
    /**
     * @param  InviteMember  $request
     */
    public function handle(Request $request): IssuedInvitation
    {
        $business = Business::query()
            ->where('ulid', $request->businessId->toString())
            ->firstOrFail();

        $inviter = User::query()
            ->where('ulid', $request->invitedById->toString())
            ->firstOrFail();

        $this->authorise($inviter, $business, $request->role);
        $this->guardDuplicate($business->id, $request->email->toString());

        $token = Token::generate();

        $invitation = Invitation::create([
            'business_id' => $business->id,
            'email' => $request->email->toString(),
            'role' => $request->role->value,
            'token_hash' => $token->hash(),
            'status' => InvitationStatus::Pending->value,
            'expires_at' => now()->addHours(72),
            'invited_by_id' => $inviter->id,
        ]);

        Event::dispatch(new InvitationIssued(
            invitationId: Id::fromString($invitation->ulid),
            businessId: $request->businessId,
            invitedById: $request->invitedById,
            email: $request->email->toString(),
            role: $request->role,
        ));

        return new IssuedInvitation(
            invitationId: Id::fromString($invitation->ulid),
            rawToken: $token->urlSafe(),
            url: route('invites.accept', ['token' => $token->urlSafe()]),
        );
    }

    private function authorise(User $inviter, Business $business, UserRole $role): void
    {
        if ($inviter->isSuperAdmin()) {
            return;
        }

        if (! $inviter->isOwner() || $inviter->business_id !== $business->id) {
            throw InvitationException::notAuthorisedForBusiness();
        }

        if ($role->is(UserRole::SuperAdmin)) {
            throw InvitationException::notAuthorisedToInvite();
        }
    }

    private function guardDuplicate(int $businessId, string $email): void
    {
        $exists = Invitation::query()
            ->where('business_id', $businessId)
            ->where('email', $email)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->exists();

        if ($exists) {
            throw InvitationException::alreadyInvited($email);
        }
    }
}
