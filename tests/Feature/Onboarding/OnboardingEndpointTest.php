<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use Illuminate\Support\Facades\Auth;

function onboardBody(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Glow Salon',
        'trn' => str_repeat('1', 15),
        'admin' => [
            'name' => 'Olivia Owner',
            'login' => 'olivia@glow.test',
            'password' => 'password123',
        ],
    ], $overrides);
}

function locationBody(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Main Branch',
        'address' => ['street' => '1 St', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours' => ['mon' => [['open' => '09:00', 'close' => '21:00']]],
    ], $overrides);
}

test('super-admin onboards a business + admin (no location)', function (): void {
    $response = $this->actingAs(superAdminUser())
        ->postJson('/admin/businesses', onboardBody())
        ->assertCreated()
        ->assertJsonStructure(['business_id', 'login']);

    $admin = User::where('email', 'olivia@glow.test')->firstOrFail();
    expect($admin->role)->toBe(UserRole::BusinessAdmin)
        ->and($admin->business_id)->not->toBeNull();

    // The response (serialized straight from the OnboardedBusiness DTO) carries the real ids.
    $response->assertJson([
        'business_id' => $admin->business->ulid,
        'login' => 'olivia@glow.test',
    ]);

    expect(Auth::attempt(['email' => 'olivia@glow.test', 'password' => 'password123']))->toBeTrue();
});

test('super-admin onboards a business with a username login (not an email)', function (): void {
    $this->actingAs(superAdminUser())
        ->postJson('/admin/businesses', onboardBody(['admin' => ['login' => 'olivia']]))
        ->assertCreated()
        ->assertJson(['login' => 'olivia']);

    // Username branch: username is set and a synthetic email is generated for it.
    $admin = User::where('username', 'olivia')->firstOrFail();
    expect($admin->role)->toBe(UserRole::BusinessAdmin)
        ->and($admin->email)->not->toBe('olivia')
        ->and($admin->email)->toContain('@');
});

test('non-super-admin cannot onboard', function (): void {
    $business = Business::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::BusinessAdmin->value, 'business_id' => $business->id]);

    $this->actingAs($admin)
        ->postJson('/admin/businesses', onboardBody())
        ->assertForbidden();
});

test('super-admin adds a location to a business', function (): void {
    $business = Business::factory()->create();

    $this->actingAs(superAdminUser())
        ->postJson("/admin/businesses/{$business->ulid}/locations", locationBody())
        ->assertCreated()
        ->assertJsonStructure(['location_id']);

    expect(Location::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});

test('add location to unknown business returns 404', function (): void {
    $this->actingAs(superAdminUser())
        ->postJson('/admin/businesses/00000000000000000000000000/locations', locationBody())
        ->assertNotFound();
});

test('add location with a malformed business id is a validation error', function (): void {
    $this->actingAs(superAdminUser())
        ->postJson('/admin/businesses/01JUNKULIDDOESNOTEXIST00/locations', locationBody())
        ->assertStatus(422)
        ->assertJsonValidationErrors('business');
});

test('non-super-admin cannot add a location', function (): void {
    $business = Business::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::BusinessAdmin->value, 'business_id' => $business->id]);

    $this->actingAs($admin)
        ->postJson("/admin/businesses/{$business->ulid}/locations", locationBody())
        ->assertForbidden();
});

test('end-to-end: onboard, admin logs in, location added', function (): void {
    $sa = superAdminUser();

    $this->actingAs($sa)->postJson('/admin/businesses', onboardBody())->assertCreated();
    expect(Auth::attempt(['email' => 'olivia@glow.test', 'password' => 'password123']))->toBeTrue();

    $business = Business::where('name', 'Glow Salon')->firstOrFail();

    $this->actingAs($sa)
        ->postJson("/admin/businesses/{$business->ulid}/locations", locationBody())
        ->assertCreated();

    expect(Location::withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});
