<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use Livewire\Volt\Volt;

function businessAdmin(Business $business): User
{
    return User::factory()->create([
        'business_id' => $business->id,
        'role' => UserRole::BusinessAdmin->value,
    ]);
}

function pageLocation(Business $business): Location
{
    return Location::create([
        'business_id' => $business->id,
        'name' => 'Branch',
        'address_json' => ['street' => 'a', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours_json' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
    ]);
}

// --- access ---------------------------------------------------------------

test('business admin can view the staff list', function (): void {
    $business = Business::factory()->create();

    $this->actingAs(businessAdmin($business))
        ->get(route('staff.index'))
        ->assertOk();
});

test('location agent cannot access staff pages', function (): void {
    $business = Business::factory()->create();
    $agent = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::LocationAgent->value]);

    $this->actingAs($agent)->get(route('staff.index'))->assertForbidden();
});

test('guests are redirected from staff pages', function (): void {
    $this->get(route('staff.index'))->assertRedirect(route('login'));
});

// --- create ---------------------------------------------------------------

test('create form makes a business admin', function (): void {
    $business = Business::factory()->create();

    $this->actingAs(businessAdmin($business));

    Volt::test('staff.create')
        ->set('name', 'Bea')
        ->set('email', 'bea@glow.test')
        ->set('password', 'password123')
        ->set('role', 'business_admin')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('staff.index'));

    $this->assertDatabaseHas('users', ['email' => 'bea@glow.test', 'role' => 'business_admin', 'business_id' => $business->id]);
});

test('create requires an email or a username', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(businessAdmin($business));

    Volt::test('staff.create')
        ->set('name', 'Nobody')
        ->set('password', 'password123')
        ->set('role', 'business_admin')
        ->call('save')
        ->assertHasErrors('email');
});

test('create requires a location for a location agent', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(businessAdmin($business));

    Volt::test('staff.create')
        ->set('name', 'Sara')
        ->set('email', 'sara@glow.test')
        ->set('password', 'password123')
        ->set('role', 'location_agent')
        ->set('locationIds', [])
        ->call('save')
        ->assertHasErrors('locationIds');
});

// --- edit / deactivate ----------------------------------------------------

test('edit can deactivate an agent', function (): void {
    $business = Business::factory()->create();
    $this->actingAs(businessAdmin($business));
    $agent = User::factory()->create(['business_id' => $business->id, 'role' => UserRole::LocationAgent->value]);

    Volt::test('staff.edit', ['user' => $agent])
        ->call('deactivate')
        ->assertHasNoErrors()
        ->assertRedirect(route('staff.index'));

    expect($agent->fresh()->status)->toBe(UserStatus::Terminated);
});

test('edit surfaces the last-admin guard as an error', function (): void {
    $business = Business::factory()->create();
    $admin = businessAdmin($business);
    $this->actingAs($admin);

    Volt::test('staff.edit', ['user' => $admin])
        ->set('status', 'on_leave')
        ->call('save')
        ->assertHasErrors('status');

    expect($admin->fresh()->status)->toBe(UserStatus::Active);
});
