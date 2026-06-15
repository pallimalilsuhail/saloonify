<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use Shared\ValueObjects\Id;

it('auto-generates a ULID and uses it as the route key', function () {
    $session = UploadSession::factory()->create();

    expect($session->ulid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($session->getRouteKeyName())->toBe('ulid');
});

it('belongs to business + customer + creator', function () {
    $session = UploadSession::factory()->create();

    expect($session->business)->toBeInstanceOf(Business::class)
        ->and($session->customer)->toBeInstanceOf(Customer::class);
});

it('casts status to UploadSessionStatus and allowed_mime to array', function () {
    $session = UploadSession::factory()->create([
        'status' => UploadSessionStatus::Active->value,
        'allowed_mime' => ['application/pdf', 'image/png'],
    ]);

    expect($session->status)->toBe(UploadSessionStatus::Active)
        ->and($session->allowed_mime)->toBe(['application/pdf', 'image/png']);
});

it('isActive returns true for active + future expiry', function () {
    $session = UploadSession::factory()->create([
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->addHour(),
    ]);

    expect($session->isActive())->toBeTrue()
        ->and($session->isExpired())->toBeFalse();
});

it('isActive returns false once expired', function () {
    $session = UploadSession::factory()->create([
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->subMinute(),
    ]);

    expect($session->isActive())->toBeFalse()
        ->and($session->isExpired())->toBeTrue();
});

it('isSubmitted + isRevoked reflect status enum', function () {
    $submitted = UploadSession::factory()->create(['status' => UploadSessionStatus::Submitted->value]);
    $revoked = UploadSession::factory()->create(['status' => UploadSessionStatus::Revoked->value]);

    expect($submitted->isSubmitted())->toBeTrue()
        ->and($submitted->isRevoked())->toBeFalse()
        ->and($revoked->isRevoked())->toBeTrue()
        ->and($revoked->isActive())->toBeFalse();
});

it('id() returns an Id value object matching the ulid', function () {
    $session = UploadSession::factory()->create();

    expect($session->id())->toBeInstanceOf(Id::class)
        ->and($session->id()->toString())->toBe($session->ulid);
});

it('is scoped by BelongsToBusiness query filter', function () {
    $a = Business::factory()->create();
    $b = Business::factory()->create();
    $cA = Customer::factory()->for($a)->create();
    $cB = Customer::factory()->for($b)->create();

    $sA = UploadSession::factory()->create(['business_id' => $a->id, 'customer_id' => $cA->id]);
    UploadSession::factory()->create(['business_id' => $b->id, 'customer_id' => $cB->id]);

    $result = UploadSession::query()
        ->tap(new BelongsToBusiness(Id::fromString($a->ulid)))
        ->get();

    expect($result->pluck('id')->all())->toBe([$sA->id]);
});
