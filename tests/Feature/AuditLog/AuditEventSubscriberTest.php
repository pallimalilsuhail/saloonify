<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\AuditLog\Models\AuditLog;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Events\InvitationConsumed;
use App\Modules\Businesses\Events\InvitationIssued;
use App\Modules\Businesses\Events\InvitationRevoked;
use App\Modules\Businesses\Events\MemberRemoved;
use App\Modules\Businesses\Events\MemberRoleChanged;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Events\CustomerCreated;
use App\Modules\DocumentRequests\Events\UploadLinkGenerated;
use App\Modules\DocumentRequests\Events\UploadLinkRevoked;
use App\Modules\Documents\Events\DocumentDeleted;
use App\Modules\Documents\Events\DocumentDownloaded;
use App\Modules\Documents\Events\DocumentViewUrlIssued;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->actor = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'w-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

function lastAuditFor(string $action): AuditLog
{
    return AuditLog::query()->where('action', $action)->orderByDesc('id')->firstOrFail();
}

it('records document.deleted from DocumentDeleted event', function () {
    Event::dispatch(new DocumentDeleted(
        documentId: Id::generate(),
        businessId: Id::fromString($this->business->ulid),
        actorId: Id::fromString($this->actor->ulid),
    ));

    $row = lastAuditFor('document.deleted');
    expect($row->actor_id)->toBe($this->actor->id)
        ->and($row->business_id)->toBe($this->business->id)
        ->and($row->target_type)->toBe('Document');
});

it('records document.downloaded from DocumentDownloaded event', function () {
    Event::dispatch(new DocumentDownloaded(
        documentId: Id::generate(),
        businessId: Id::fromString($this->business->ulid),
        actorId: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('document.downloaded')->actor_id)->toBe($this->actor->id);
});

it('records document.view_url_issued from DocumentViewUrlIssued event', function () {
    Event::dispatch(new DocumentViewUrlIssued(
        documentId: Id::generate(),
        businessId: Id::fromString($this->business->ulid),
        actorId: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('document.view_url_issued')->actor_id)->toBe($this->actor->id);
});

it('records upload_link.generated + .revoked from upload-link events', function () {
    $sessionId = Id::generate();
    $customerId = Id::generate();

    Event::dispatch(new UploadLinkGenerated(
        sessionId: $sessionId,
        businessId: Id::fromString($this->business->ulid),
        customerId: $customerId,
        generatedById: Id::fromString($this->actor->ulid),
    ));

    Event::dispatch(new UploadLinkRevoked(
        sessionId: $sessionId,
        businessId: Id::fromString($this->business->ulid),
        customerId: $customerId,
        revokedById: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('upload_link.generated')->target_id)->toBe($sessionId->toString())
        ->and(lastAuditFor('upload_link.revoked')->target_id)->toBe($sessionId->toString());
});

it('records customer.created from CustomerCreated event', function () {
    Event::dispatch(new CustomerCreated(
        customerId: Id::generate(),
        businessId: Id::fromString($this->business->ulid),
        createdById: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('customer.created')->actor_id)->toBe($this->actor->id);
});

it('records invitation lifecycle events', function () {
    $invitationId = Id::generate();

    Event::dispatch(new InvitationIssued(
        invitationId: $invitationId,
        businessId: Id::fromString($this->business->ulid),
        invitedById: Id::fromString($this->actor->ulid),
        email: 'new@x.com',
        role: UserRole::Member,
    ));

    Event::dispatch(new InvitationRevoked(
        invitationId: $invitationId,
        businessId: Id::fromString($this->business->ulid),
        revokedById: Id::fromString($this->actor->ulid),
    ));

    Event::dispatch(new InvitationConsumed(
        invitationId: $invitationId,
        businessId: Id::fromString($this->business->ulid),
        userId: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('invitation.issued')->meta)->toMatchArray(['email' => 'new@x.com', 'role' => 'member'])
        ->and(lastAuditFor('invitation.revoked')->target_id)->toBe($invitationId->toString())
        ->and(lastAuditFor('invitation.consumed')->target_id)->toBe($invitationId->toString());
});

it('records member.role_changed + member.removed events', function () {
    Event::dispatch(new MemberRoleChanged(
        memberId: Id::fromString($this->actor->ulid),
        businessId: Id::fromString($this->business->ulid),
        changedById: Id::fromString($this->actor->ulid),
        fromRole: UserRole::Member,
        toRole: UserRole::Owner,
    ));

    Event::dispatch(new MemberRemoved(
        memberId: Id::fromString($this->actor->ulid),
        businessId: Id::fromString($this->business->ulid),
        removedById: Id::fromString($this->actor->ulid),
    ));

    expect(lastAuditFor('member.role_changed')->meta)->toMatchArray(['from' => 'member', 'to' => 'owner'])
        ->and(lastAuditFor('member.removed')->target_id)->toBe($this->actor->ulid);
});

it('records auth.login.success from Login event', function () {
    Event::dispatch(new Login('web', $this->actor, false));

    $row = lastAuditFor('auth.login.success');
    expect($row->actor_id)->toBe($this->actor->id)
        ->and($row->meta)->toMatchArray(['guard' => 'web']);
});

it('records auth.login.failed with anonymous actor when no user is bound', function () {
    Event::dispatch(new Failed('web', null, ['email' => 'ghost@x.com']));

    $row = lastAuditFor('auth.login.failed');
    expect($row->actor_id)->toBeNull()
        ->and($row->actor_type)->toBe('anonymous')
        ->and($row->meta)->toMatchArray(['email' => 'ghost@x.com']);
});
