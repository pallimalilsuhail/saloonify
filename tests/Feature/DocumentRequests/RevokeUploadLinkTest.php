<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadLinkRevoked;
use App\Modules\DocumentRequests\Exceptions\CannotRevokeUploadLink;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\RevokeUploadLink\RevokeUploadLink;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
    $this->session = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => UploadSessionStatus::Active->value,
    ]);
    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

it('flips an active session to revoked and stamps revoked_at', function () {
    Mediator::dispatch(app(RevokeUploadLink::class, [
        'sessionId' => Id::fromString($this->session->ulid),
        'revokedById' => Id::fromString($this->owner->ulid),
    ]));

    $fresh = $this->session->fresh();
    expect($fresh->status)->toBe(UploadSessionStatus::Revoked)
        ->and($fresh->revoked_at)->not->toBeNull();
});

it('dispatches UploadLinkRevoked with session + business + customer + actor ids', function () {
    Event::fake([UploadLinkRevoked::class]);

    Mediator::dispatch(app(RevokeUploadLink::class, [
        'sessionId' => Id::fromString($this->session->ulid),
        'revokedById' => Id::fromString($this->owner->ulid),
    ]));

    Event::assertDispatched(
        UploadLinkRevoked::class,
        fn (UploadLinkRevoked $e) => $e->sessionId->toString() === $this->session->ulid
            && $e->businessId->toString() === $this->business->ulid
            && $e->customerId->toString() === $this->customer->ulid
            && $e->revokedById?->toString() === $this->owner->ulid
    );
});

it('throws CannotRevokeUploadLink when the session is not active', function () {
    $this->session->update(['status' => UploadSessionStatus::Submitted->value]);

    Mediator::dispatch(app(RevokeUploadLink::class, [
        'sessionId' => Id::fromString($this->session->ulid),
        'revokedById' => Id::fromString($this->owner->ulid),
    ]));
})->throws(CannotRevokeUploadLink::class, 'Only active');

it('throws when the actor belongs to a different business', function () {
    $otherBusiness = Business::factory()->create();
    $stranger = User::create([
        'name' => 'Stranger',
        'email' => 's@x.com',
        'workos_id' => 'ws-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $otherBusiness->id,
    ]);

    Mediator::dispatch(app(RevokeUploadLink::class, [
        'sessionId' => Id::fromString($this->session->ulid),
        'revokedById' => Id::fromString($stranger->ulid),
    ]));
})->throws(CannotRevokeUploadLink::class, 'not authorised');

it('allows a super_admin to revoke any business session', function () {
    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@x.com',
        'workos_id' => 'wsa-1',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    Mediator::dispatch(app(RevokeUploadLink::class, [
        'sessionId' => Id::fromString($this->session->ulid),
        'revokedById' => Id::fromString($admin->ulid),
    ]));

    expect($this->session->fresh()->status)->toBe(UploadSessionStatus::Revoked);
});
