<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use Illuminate\Support\Facades\Route;

function makeLocation(Business $business, string $name = 'Branch'): Location
{
    return Location::create([
        'business_id' => $business->id,
        'name' => $name,
        'address_json' => ['street' => 'x', 'city' => 'Dubai', 'emirate' => 'Dubai', 'country' => 'AE'],
        'opening_hours_json' => ['mon' => ['open' => '09:00', 'close' => '21:00']],
    ]);
}

// --- BusinessScope ---------------------------------------------------------

test('unbound (super-admin/console) context sees all businesses rows', function (): void {
    $l1 = makeLocation(Business::factory()->create());
    $l2 = makeLocation(Business::factory()->create());

    expect(Location::count())->toBe(2);
});

test('bound tenant only sees its own rows; cross-business fetch returns nothing', function (): void {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $l1 = makeLocation($b1);
    $l2 = makeLocation($b2);

    app()->instance('tenant.business_id', $b1->id);

    expect(Location::count())->toBe(1)
        ->and(Location::first()->id)->toBe($l1->id)
        ->and(Location::find($l2->id))->toBeNull();
});

// --- TenantContext middleware ---------------------------------------------

test('tenant context binds business for a business user', function (): void {
    Route::middleware(['web', 'auth'])->get('/_t/ctx', fn () => app()->bound('tenant.business_id')
        ? (string) app('tenant.business_id')
        : 'none');

    $business = Business::factory()->create();
    $user = User::factory()->create(['role' => UserRole::BusinessAdmin->value, 'business_id' => $business->id]);

    $this->actingAs($user)->get('/_t/ctx')->assertOk()->assertSee((string) $business->id);
});

test('tenant context binds nothing for a super admin', function (): void {
    Route::middleware(['web', 'auth'])->get('/_t/ctx2', fn () => app()->bound('tenant.business_id')
        ? 'bound'
        : 'none');

    $user = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

    $this->actingAs($user)->get('/_t/ctx2')->assertOk()->assertSee('none');
});

test('a business user without a business is forbidden', function (): void {
    Route::middleware(['web', 'auth'])->get('/_t/ctx3', fn () => 'ok');

    $user = User::factory()->create(['role' => UserRole::BusinessAdmin->value, 'business_id' => null]);

    $this->actingAs($user)->get('/_t/ctx3')->assertForbidden();
});

// --- Role gates ------------------------------------------------------------

dataset('roleGates', [
    'super_admin' => ['super_admin', UserRole::SuperAdmin],
    'business_admin' => ['business_admin', UserRole::BusinessAdmin],
    'location_agent' => ['location_agent', UserRole::LocationAgent],
]);

test('role gate allows the matching role', function (string $alias, UserRole $role): void {
    Route::middleware(['web', 'auth', $alias])->get("/_t/gate-$alias", fn () => 'ok');

    $business = $role === UserRole::SuperAdmin ? null : Business::factory()->create();
    $user = User::factory()->create([
        'role' => $role->value,
        'business_id' => $business?->id,
    ]);

    $this->actingAs($user)->get("/_t/gate-$alias")->assertOk();
})->with('roleGates');

test('role gate blocks a non-matching role', function (): void {
    Route::middleware(['web', 'auth', 'super_admin'])->get('/_t/gate-only-sa', fn () => 'ok');

    $business = Business::factory()->create();
    $agent = User::factory()->create(['role' => UserRole::LocationAgent->value, 'business_id' => $business->id]);

    $this->actingAs($agent)->get('/_t/gate-only-sa')->assertForbidden();
});

test('role gate blocks guests', function (): void {
    Route::middleware(['web', 'business_admin'])->get('/_t/gate-guest', fn () => 'ok');

    $this->get('/_t/gate-guest')->assertForbidden();
});
