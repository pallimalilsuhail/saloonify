<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\DTOs\IssuedInvitation;
use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\CreateBusiness\CreateBusiness;
use App\Modules\Businesses\UseCases\InviteMember\InviteMember;
use App\Modules\Businesses\UseCases\SuspendBusiness\SuspendBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use Livewire\Livewire;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);

    $this->superAdmin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($this->superAdmin);
});

it('dispatches CreateBusiness with the form values when the create action runs', function () {
    $fake = Mediator::fake(CreateBusiness::class, Id::generate());

    Livewire::test('pages::admin.businesses.index')
        ->set('name', 'Acme Corp')
        ->set('slug', 'acme-corp')
        ->call('create')
        ->assertHasNoErrors();

    $fake->assertDispatched(CreateBusiness::class, fn (CreateBusiness $cmd) => $cmd->name === 'Acme Corp' && $cmd->slug === 'acme-corp');
});

it('passes slug=null when slug field is left blank', function () {
    $fake = Mediator::fake(CreateBusiness::class, Id::generate());

    Livewire::test('pages::admin.businesses.index')
        ->set('name', 'Acme Corp')
        ->set('slug', '')
        ->call('create')
        ->assertHasNoErrors();

    $fake->assertDispatched(CreateBusiness::class, fn (CreateBusiness $cmd) => $cmd->name === 'Acme Corp' && $cmd->slug === null);
});

it('does not dispatch CreateBusiness when validation fails', function () {
    $fake = Mediator::fake(CreateBusiness::class, Id::generate());

    Livewire::test('pages::admin.businesses.index')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name']);

    $fake->assertNotDispatched(CreateBusiness::class);
});

it('dispatches SuspendBusiness with the correct ULID', function () {
    $business = Business::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => BusinessStatus::Active->value,
    ]);

    $fake = Mediator::fake(SuspendBusiness::class, Id::fromString($business->ulid));

    Livewire::test('pages::admin.businesses.index')
        ->call('suspend', $business->ulid)
        ->assertHasNoErrors();

    $fake->assertDispatched(SuspendBusiness::class, fn (SuspendBusiness $cmd) => $cmd->businessId->toString() === $business->ulid);
});

it('dispatches InviteMember with role=owner from the invite-owner action', function () {
    $business = Business::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => BusinessStatus::Active->value,
    ]);

    $fake = Mediator::fake(InviteMember::class, new IssuedInvitation(
        invitationId: Id::generate(),
        rawToken: 'sample-token',
        url: 'http://test.local/invites/sample-token',
    ));

    Livewire::test('pages::admin.businesses.index')
        ->call('openInviteOwner', $business->ulid, $business->name)
        ->set('inviteEmail', 'newowner@acme.com')
        ->call('sendOwnerInvite')
        ->assertHasNoErrors()
        ->assertSet('invitationUrl', 'http://test.local/invites/sample-token');

    $fake->assertDispatched(
        InviteMember::class,
        fn (InviteMember $cmd) => $cmd->businessId->toString() === $business->ulid
            && $cmd->email->toString() === 'newowner@acme.com'
            && $cmd->role === UserRole::Owner
            && $cmd->invitedById->toString() === $this->superAdmin->ulid
    );
});

it('does not dispatch InviteMember when email is missing', function () {
    $business = Business::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => BusinessStatus::Active->value,
    ]);

    $fake = Mediator::fake(InviteMember::class, null);

    Livewire::test('pages::admin.businesses.index')
        ->call('openInviteOwner', $business->ulid, $business->name)
        ->set('inviteEmail', '')
        ->call('sendOwnerInvite')
        ->assertHasErrors(['inviteEmail']);

    $fake->assertNotDispatched(InviteMember::class);
});
