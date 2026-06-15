<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Models\Business;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\Models\Customer;
use Shared\ValueObjects\Id;

it('auto-generates a ULID on create and uses it as the route key', function () {
    $business = Business::factory()->create();

    $customer = Customer::create([
        'business_id' => $business->id,
        'name' => 'Test',
        'phone' => '+971501234567',
        'email' => 'test@example.com',
    ]);

    expect($customer->ulid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($customer->getRouteKeyName())->toBe('ulid');
});

it('belongs to a business', function () {
    $business = Business::factory()->create();
    $customer = Customer::factory()->for($business)->create();

    expect($customer->business)->toBeInstanceOf(Business::class)
        ->and($customer->business->id)->toBe($business->id);
});

it('belongs to a creator user', function () {
    $business = Business::factory()->create();
    $user = User::create([
        'name' => 'Owner',
        'email' => 'owner@x.com',
        'workos_id' => 'w1',
        'avatar' => '',
        'business_id' => $business->id,
    ]);

    $customer = Customer::create([
        'business_id' => $business->id,
        'name' => 'Test',
        'phone' => '+971501234567',
        'created_by_id' => $user->id,
    ]);

    expect($customer->createdBy)->toBeInstanceOf(User::class)
        ->and($customer->createdBy->id)->toBe($user->id);
});

it('soft deletes', function () {
    $customer = Customer::factory()->create();
    $id = $customer->id;

    $customer->delete();

    expect(Customer::find($id))->toBeNull()
        ->and(Customer::withTrashed()->find($id))->not->toBeNull();
});

it('is scoped by BelongsToBusiness query filter', function () {
    $a = Business::factory()->create();
    $b = Business::factory()->create();

    $cA = Customer::factory()->for($a)->create();
    $cB = Customer::factory()->for($b)->create();

    $resultA = Customer::query()
        ->tap(new BelongsToBusiness(Id::fromString($a->ulid)))
        ->get();

    expect($resultA->pluck('id')->all())->toBe([$cA->id]);
});

it('id() returns the ULID as an Id value object', function () {
    $customer = Customer::factory()->create();

    expect($customer->id())->toBeInstanceOf(Id::class)
        ->and($customer->id()->toString())->toBe($customer->ulid);
});
