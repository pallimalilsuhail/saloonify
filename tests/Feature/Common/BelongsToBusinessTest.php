<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\ValueObjects\Id;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->businessA = Business::create([
        'name' => 'A',
        'slug' => 'business-a',
        'status' => BusinessStatus::Active->value,
    ]);
    $this->businessB = Business::create([
        'name' => 'B',
        'slug' => 'business-b',
        'status' => BusinessStatus::Active->value,
    ]);

    $this->userA = User::create([
        'name' => 'Alice',
        'email' => 'alice@a.com',
        'workos_id' => 'wa-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->businessA->id,
    ]);

    $this->userB = User::create([
        'name' => 'Bob',
        'email' => 'bob@b.com',
        'workos_id' => 'wb-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
        'business_id' => $this->businessB->id,
    ]);
});

it('returns only records that belong to the supplied business', function () {
    $resultA = User::query()
        ->tap(new BelongsToBusiness(Id::fromString($this->businessA->ulid)))
        ->get();

    $resultB = User::query()
        ->tap(new BelongsToBusiness(Id::fromString($this->businessB->ulid)))
        ->get();

    expect($resultA->pluck('email')->all())->toBe(['alice@a.com'])
        ->and($resultB->pluck('email')->all())->toBe(['bob@b.com']);
});

it('returns no records when the business id does not match any business', function () {
    $unknownId = Id::generate();

    $result = User::query()
        ->tap(new BelongsToBusiness($unknownId))
        ->get();

    expect($result)->toHaveCount(0);
});

it('composes with other where clauses', function () {
    $result = User::query()
        ->tap(new BelongsToBusiness(Id::fromString($this->businessA->ulid)))
        ->where('email', 'bob@b.com')
        ->get();

    expect($result)->toHaveCount(0);
});
