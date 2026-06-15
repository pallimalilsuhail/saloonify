<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Invitation;
use App\Modules\Businesses\UseCases\ConsumeInvite\ConsumeInvite;
use App\Modules\Businesses\UseCases\InviteMember\InviteMember;
use App\Modules\Businesses\UseCases\RemoveMember\RemoveMember;
use App\Modules\Businesses\UseCases\RevokeInvite\RevokeInvite;
use App\Modules\Businesses\UseCases\UpdateMemberRole\UpdateMemberRole;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

beforeEach(function () {
    $this->business = Business::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => BusinessStatus::Active->value,
    ]);

    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'owner@acme.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);

    $this->superAdmin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);
});

it('owner can invite a member to their own business', function () {
    $command = app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'new@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]);

    $result = Mediator::dispatch($command);

    expect($result->rawToken)->toBeString()
        ->and($result->url)->toContain('/invites/')
        ->and(Invitation::where('email', 'new@acme.com')->exists())->toBeTrue();
});

it('owner cannot invite a super admin', function () {
    $command = app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'evil@acme.com']),
        'role' => UserRole::SuperAdmin,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]);

    Mediator::dispatch($command);
})->throws(InvitationException::class);

it('owner cannot invite to a different business', function () {
    $other = Business::create([
        'name' => 'Other',
        'slug' => 'other',
        'status' => BusinessStatus::Active->value,
    ]);

    $command = app(InviteMember::class, [
        'businessId' => Id::fromString($other->ulid),
        'email' => app(Email::class, ['email' => 'x@other.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]);

    Mediator::dispatch($command);
})->throws(InvitationException::class);

it('rejects a duplicate pending invite for the same email', function () {
    $args = [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'dup@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ];

    Mediator::dispatch(app(InviteMember::class, $args));
    Mediator::dispatch(app(InviteMember::class, $args));
})->throws(InvitationException::class);

it('consumes a valid invitation and assigns business + role', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'consumer@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    $newUser = User::create([
        'name' => 'Consumer',
        'email' => 'consumer@acme.com',
        'workos_id' => 'wc-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
    ]);

    $result = Mediator::dispatch(app(ConsumeInvite::class, [
        'token' => Token::fromUrlSafe($invite->rawToken),
        'user' => $newUser,
    ]));

    expect($result->userId->toString())->toBe($newUser->ulid)
        ->and($result->businessId->toString())->toBe($this->business->ulid)
        ->and($newUser->fresh()->business_id)->toBe($this->business->id)
        ->and($newUser->fresh()->role)->toBe(UserRole::Member);

    $invitation = Invitation::query()->where('ulid', $invite->invitationId->toString())->first();
    expect($invitation->status)->toBe(InvitationStatus::Accepted)
        ->and($invitation->accepted_at)->not->toBeNull();
});

it('rejects consume when email does not match the invitation', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'right@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    $newUser = User::create([
        'name' => 'Wrong',
        'email' => 'wrong@acme.com',
        'workos_id' => 'ww-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
    ]);

    Mediator::dispatch(app(ConsumeInvite::class, [
        'token' => Token::fromUrlSafe($invite->rawToken),
        'user' => $newUser,
    ]));
})->throws(InvitationException::class);

it('rejects consume after the invitation expires', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'late@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    Invitation::query()
        ->where('ulid', $invite->invitationId->toString())
        ->update(['expires_at' => now()->subMinute()]);

    $newUser = User::create([
        'name' => 'Late',
        'email' => 'late@acme.com',
        'workos_id' => 'wl-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
    ]);

    Mediator::dispatch(app(ConsumeInvite::class, [
        'token' => Token::fromUrlSafe($invite->rawToken),
        'user' => $newUser,
    ]));
})->throws(InvitationException::class);

it('rejects consume for an already-accepted invitation', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'twice@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    $newUser = User::create([
        'name' => 'Twice',
        'email' => 'twice@acme.com',
        'workos_id' => 'wt-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
    ]);

    Mediator::dispatch(app(ConsumeInvite::class, [
        'token' => Token::fromUrlSafe($invite->rawToken),
        'user' => $newUser,
    ]));

    Mediator::dispatch(app(ConsumeInvite::class, [
        'token' => Token::fromUrlSafe($invite->rawToken),
        'user' => $newUser,
    ]));
})->throws(InvitationException::class);

it('owner can revoke a pending invitation', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'revoke@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    Mediator::dispatch(app(RevokeInvite::class, [
        'invitationId' => $invite->invitationId,
        'actorId' => Id::fromString($this->owner->ulid),
    ]));

    $invitation = Invitation::query()->where('ulid', $invite->invitationId->toString())->first();
    expect($invitation->status)->toBe(InvitationStatus::Revoked)
        ->and($invitation->revoked_at)->not->toBeNull();
});

it('owner can change a member role within their business', function () {
    $member = User::create([
        'name' => 'Member',
        'email' => 'm@acme.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    Mediator::dispatch(app(UpdateMemberRole::class, [
        'memberId' => Id::fromString($member->ulid),
        'newRole' => UserRole::Owner,
        'actorId' => Id::fromString($this->owner->ulid),
    ]));

    expect($member->fresh()->role)->toBe(UserRole::Owner);
});

it('rejects changing your own role', function () {
    Mediator::dispatch(app(UpdateMemberRole::class, [
        'memberId' => Id::fromString($this->owner->ulid),
        'newRole' => UserRole::Member,
        'actorId' => Id::fromString($this->owner->ulid),
    ]));
})->throws(InvitationException::class);

it('removes a member from the business', function () {
    $member = User::create([
        'name' => 'Member',
        'email' => 'm2@acme.com',
        'workos_id' => 'wm-2',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    Mediator::dispatch(app(RemoveMember::class, [
        'memberId' => Id::fromString($member->ulid),
        'actorId' => Id::fromString($this->owner->ulid),
    ]));

    $fresh = $member->fresh();
    expect($fresh->business_id)->toBeNull()
        ->and($fresh->role)->toBe(UserRole::Member);
});

it('public /invites/{token} stashes the invite in session and redirects to /login', function () {
    $invite = Mediator::dispatch(app(InviteMember::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'email' => app(Email::class, ['email' => 'guest@acme.com']),
        'role' => UserRole::Member,
        'invitedById' => Id::fromString($this->owner->ulid),
    ]));

    $this->get(route('invites.accept', ['token' => $invite->rawToken]))
        ->assertRedirect('/login')
        ->assertSessionHas('pending_invitation_token', $invite->rawToken)
        ->assertSessionHas('pending_invitation_email', 'guest@acme.com');
});
