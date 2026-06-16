<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;

test('business auto-generates a unique slug and de-duplicates collisions', function (): void {
    $a = Business::create(['name' => 'Glow Salon', 'trn' => str_repeat('1', 15)]);
    $b = Business::create(['name' => 'Glow Salon', 'trn' => str_repeat('2', 15)]);

    expect($a->slug)->toBe('glow-salon');
    expect($b->slug)->toBe('glow-salon-2');
    expect($a->fresh()->country)->toBe('AE');
    expect($a->fresh()->currency)->toBe('AED');
});

test('location round-trips json and cascades on business delete', function (): void {
    $business = Business::factory()->create();

    $location = Location::create([
        'business_id' => $business->id,
        'name' => 'Branch 1',
        'address_json' => ['street' => 'A St', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours_json' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
    ]);

    expect($location->fresh()->address_json['city'])->toBe('Dubai');

    // soft delete leaves the location intact
    $business->delete();
    expect(Location::find($location->id))->not->toBeNull();

    // hard delete cascades at the DB level
    $business->forceDelete();
    expect(Location::withTrashed()->find($location->id))->toBeNull();
});

test('user belongs to many locations and casts role + status', function (): void {
    $business = Business::factory()->create();

    $makeLocation = fn (string $name) => Location::create([
        'business_id' => $business->id,
        'name' => $name,
        'address_json' => ['street' => 'x', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours_json' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
    ]);

    $a = $makeLocation('Branch A');
    $b = $makeLocation('Branch B');

    $user = User::factory()->create([
        'business_id' => $business->id,
        'role' => UserRole::LocationAgent->value,
        'status' => UserStatus::Active->value,
    ]);
    $user->locations()->attach([$a->id, $b->id]);

    expect($user->locations)->toHaveCount(2)
        ->and($user->role)->toBe(UserRole::LocationAgent)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->isLocationAgent())->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse();
});
