<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\DTOs\IssuedInvitation;
use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Invitation;
use App\Modules\Businesses\UseCases\InviteMember\InviteMember;
use App\Modules\Businesses\UseCases\RemoveMember\RemoveMember;
use App\Modules\Businesses\UseCases\RevokeInvite\RevokeInvite;
use App\Modules\Businesses\UseCases\UpdateMemberRole\UpdateMemberRole;
use AvoqadoDev\UseCase\Facades\Mediator;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Livewire;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);

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

    $this->actingAs($this->owner);
});

it('dispatches InviteMember with the form values when the invite action runs', function () {
    $fake = Mediator::fake(InviteMember::class, new IssuedInvitation(
        invitationId: Id::generate(),
        rawToken: 'tok',
        url: 'http://test.local/invites/tok',
    ));

    Livewire::test('pages::members.index')
        ->call('openInvite')
        ->set('email', 'new@acme.com')
        ->set('role', 'member')
        ->call('invite')
        ->assertHasNoErrors()
        ->assertSet('invitationUrl', 'http://test.local/invites/tok');

    $fake->assertDispatched(
        InviteMember::class,
        fn (InviteMember $cmd) => $cmd->businessId->toString() === $this->business->ulid
            && $cmd->email->toString() === 'new@acme.com'
            && $cmd->role === UserRole::Member
            && $cmd->invitedById->toString() === $this->owner->ulid
    );
});

it('does not dispatch InviteMember when validation fails', function () {
    $fake = Mediator::fake(InviteMember::class, null);

    Livewire::test('pages::members.index')
        ->set('email', 'not-an-email')
        ->call('invite')
        ->assertHasErrors(['email']);

    $fake->assertNotDispatched(InviteMember::class);
});

it('dispatches RevokeInvite with the invitation ULID + actor ULID', function () {
    $invitation = Invitation::create([
        'business_id' => $this->business->id,
        'email' => 'pending@acme.com',
        'role' => UserRole::Member->value,
        'token_hash' => str_repeat('a', 64),
        'status' => 'pending',
        'expires_at' => now()->addHours(72),
    ]);

    $fake = Mediator::fake(RevokeInvite::class, Id::fromString($invitation->ulid));

    Livewire::test('pages::members.index')
        ->call('revoke', $invitation->ulid);

    $fake->assertDispatched(
        RevokeInvite::class,
        fn (RevokeInvite $cmd) => $cmd->invitationId->toString() === $invitation->ulid
            && $cmd->actorId->toString() === $this->owner->ulid
    );
});

it('dispatches UpdateMemberRole with the new role', function () {
    $member = User::create([
        'name' => 'Member',
        'email' => 'm@acme.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    $fake = Mediator::fake(UpdateMemberRole::class, Id::fromString($member->ulid));

    Livewire::test('pages::members.index')
        ->call('changeRole', $member->ulid, 'owner');

    $fake->assertDispatched(
        UpdateMemberRole::class,
        fn (UpdateMemberRole $cmd) => $cmd->memberId->toString() === $member->ulid
            && $cmd->newRole === UserRole::Owner
            && $cmd->actorId->toString() === $this->owner->ulid
    );
});

it('dispatches RemoveMember with the member ULID + actor ULID', function () {
    $member = User::create([
        'name' => 'Member',
        'email' => 'm@acme.com',
        'workos_id' => 'wm-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->business->id,
    ]);

    $fake = Mediator::fake(RemoveMember::class, Id::fromString($member->ulid));

    Livewire::test('pages::members.index')
        ->call('remove', $member->ulid);

    $fake->assertDispatched(
        RemoveMember::class,
        fn (RemoveMember $cmd) => $cmd->memberId->toString() === $member->ulid
            && $cmd->actorId->toString() === $this->owner->ulid
    );
});
