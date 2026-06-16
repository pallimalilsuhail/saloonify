<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use App\Modules\Staff\DTOs\StaffCreated;
use App\Modules\Staff\UseCases\CreateStaff\CreateStaff;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Support\Facades\Auth;

function staffLocation(Business $business): Location
{
    return Location::create([
        'business_id' => $business->id,
        'name' => 'Branch',
        'address_json' => ['street' => 'a', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours_json' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
    ]);
}

test('creates a location agent with email + location memberships', function (): void {
    $business = Business::factory()->create();
    $location = staffLocation($business);

    $result = Mediator::dispatch(new CreateStaff(
        businessId: $business->id,
        name: 'Sara',
        email: 'sara@glow.test',
        username: null,
        password: 'password123',
        role: UserRole::LocationAgent,
        locationIds: [$location->id],
    ));

    expect($result)->toBeInstanceOf(StaffCreated::class);

    $user = User::where('email', 'sara@glow.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::LocationAgent)
        ->and($user->business_id)->toBe($business->id)
        ->and($user->locations()->count())->toBe(1);

    expect(Auth::attempt(['email' => 'sara@glow.test', 'password' => 'password123']))->toBeTrue();
});

test('username-only staff gets a synthetic email', function (): void {
    $business = Business::factory()->create();
    $location = staffLocation($business);

    $result = Mediator::dispatch(new CreateStaff(
        businessId: $business->id,
        name: 'Ali',
        email: null,
        username: 'ali',
        password: 'password123',
        role: UserRole::LocationAgent,
        locationIds: [$location->id],
    ));

    $user = User::where('username', 'ali')->firstOrFail();
    expect($user->email)->toEndWith('.saloonify.local')
        ->and($result->email)->toBe($user->email);
});

test('business admin gets no location memberships', function (): void {
    $business = Business::factory()->create();

    Mediator::dispatch(new CreateStaff(
        businessId: $business->id,
        name: 'Bea',
        email: 'bea@glow.test',
        username: null,
        password: 'password123',
        role: UserRole::BusinessAdmin,
        locationIds: [],
    ));

    $user = User::where('email', 'bea@glow.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::BusinessAdmin)
        ->and($user->locations()->count())->toBe(0);
});
