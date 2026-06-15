<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadLinkGenerated;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\GenerateUploadLink\GenerateUploadLink;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->customer = Customer::factory()->for($this->business)->create();
    $this->actor = User::create([
        'name' => 'Owner',
        'email' => 'o@x.com',
        'workos_id' => 'wo-1',
        'avatar' => '',
        'role' => UserRole::Owner->value,
        'business_id' => $this->business->id,
    ]);
});

it('returns an IssuedUploadLink DTO with raw token + url + expiry', function () {
    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->actor->ulid),
    ]));

    expect($result)->toBeInstanceOf(IssuedUploadLink::class)
        ->and($result->rawToken)->toBeString()
        ->and($result->url)->toContain('/u/'.$result->rawToken)
        ->and($result->expiresAt->toIso8601String())->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('persists only the sha256 hash of the token, never the raw value', function () {
    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    $session = UploadSession::query()->where('ulid', $result->sessionId->toString())->firstOrFail();
    $expectedHash = Token::fromUrlSafe($result->rawToken)->hash();

    expect($session->token_hash)->toBe($expectedHash)
        ->and($session->token_hash)->not->toBe($result->rawToken)
        ->and(strlen($session->token_hash))->toBe(64);
});

it('creates the session with status=active and the configured limits', function () {
    config()->set('uploads.expiry_minutes', 60);
    config()->set('uploads.max_files', 20);
    config()->set('uploads.max_bytes', 25 * 1024 * 1024);

    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    $session = UploadSession::query()->where('ulid', $result->sessionId->toString())->firstOrFail();

    expect($session->status)->toBe(UploadSessionStatus::Active)
        ->and($session->max_files)->toBe(20)
        ->and($session->max_bytes)->toBe(25 * 1024 * 1024)
        ->and($session->allowed_mime)->toBeArray();
});

it('honours the request expiry override', function () {
    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'expiryMinutes' => 5,
    ]));

    $session = UploadSession::query()->where('ulid', $result->sessionId->toString())->firstOrFail();

    expect($session->expires_at->diffInMinutes(now()->addMinutes(5)))->toBeLessThan(1);
});

it('falls back to config expiry when none is supplied', function () {
    config()->set('uploads.expiry_minutes', 30);

    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    $session = UploadSession::query()->where('ulid', $result->sessionId->toString())->firstOrFail();

    expect($session->expires_at->diffInMinutes(now()->addMinutes(30)))->toBeLessThan(1);
});

it('produces a different token + hash on every call (entropy check)', function () {
    $a = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));
    $b = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));

    expect($a->rawToken)->not->toBe($b->rawToken);

    $hashA = UploadSession::query()->where('ulid', $a->sessionId->toString())->value('token_hash');
    $hashB = UploadSession::query()->where('ulid', $b->sessionId->toString())->value('token_hash');

    expect($hashA)->not->toBe($hashB);
});

it('throws when the customer is in a different business (cross-tenant)', function () {
    $other = Business::factory()->create();

    Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($other->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
    ]));
})->throws(ModelNotFoundException::class);

it('dispatches UploadLinkGenerated with session + business + customer + actor', function () {
    Event::fake([UploadLinkGenerated::class]);

    $result = Mediator::dispatch(app(GenerateUploadLink::class, [
        'businessId' => Id::fromString($this->business->ulid),
        'customerId' => Id::fromString($this->customer->ulid),
        'generatedById' => Id::fromString($this->actor->ulid),
    ]));

    Event::assertDispatched(
        UploadLinkGenerated::class,
        fn (UploadLinkGenerated $e) => $e->sessionId->toString() === $result->sessionId->toString()
            && $e->businessId->toString() === $this->business->ulid
            && $e->customerId->toString() === $this->customer->ulid
            && $e->generatedById?->toString() === $this->actor->ulid
    );
});
