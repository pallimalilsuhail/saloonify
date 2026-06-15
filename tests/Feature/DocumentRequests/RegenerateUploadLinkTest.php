<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadLinkGenerated;
use App\Modules\DocumentRequests\Events\UploadLinkRevoked;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\RegenerateUploadLink\RegenerateUploadLink;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
    $this->owner = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

it('revokes existing active sessions and issues a new one', function () {
    $existing = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => UploadSessionStatus::Active->value,
    ]);

    $result = Mediator::dispatch(app(RegenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->owner->ulid),
    ]));

    expect($result)->toBeInstanceOf(IssuedUploadLink::class)
        ->and($existing->fresh()->status)->toBe(UploadSessionStatus::Revoked);

    $newest = UploadSession::query()
        ->where('customer_id', $this->customer->id)
        ->orderByDesc('created_at')
        ->first();

    expect($newest->status)->toBe(UploadSessionStatus::Active)
        ->and($newest->ulid)->not->toBe($existing->ulid);
});

it('leaves submitted + revoked sessions alone (only revokes Active ones)', function () {
    $submitted = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => UploadSessionStatus::Submitted->value,
    ]);
    $previouslyRevoked = UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => UploadSessionStatus::Revoked->value,
    ]);

    Mediator::dispatch(app(RegenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->owner->ulid),
    ]));

    expect($submitted->fresh()->status)->toBe(UploadSessionStatus::Submitted)
        ->and($previouslyRevoked->fresh()->status)->toBe(UploadSessionStatus::Revoked);
});

it('still issues a fresh link when there are no active sessions', function () {
    $result = Mediator::dispatch(app(RegenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->owner->ulid),
    ]));

    expect(UploadSession::count())->toBe(1)
        ->and($result->rawToken)->toBeString();
});

it('dispatches UploadLinkRevoked + UploadLinkGenerated events', function () {
    Event::fake([UploadLinkRevoked::class, UploadLinkGenerated::class]);

    UploadSession::factory()->create([
        'business_id' => $this->business->id,
        'customer_id' => $this->customer->id,
        'status' => UploadSessionStatus::Active->value,
    ]);

    Mediator::dispatch(app(RegenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->owner->ulid),
    ]));

    Event::assertDispatched(UploadLinkRevoked::class);
    Event::assertDispatched(UploadLinkGenerated::class);
});

it('throws when the customer is in a different business (cross-tenant)', function () {
    $other = Business::factory()->create();

    Mediator::dispatch(app(RegenerateUploadLink::class, [
        'businessId' => Id::fromString($other->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->owner->ulid),
    ]));
})->throws(ModelNotFoundException::class);
