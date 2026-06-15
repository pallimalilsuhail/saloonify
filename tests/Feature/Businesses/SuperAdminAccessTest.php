<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;

beforeEach(function () {
    $this->withoutMiddleware(ValidateSessionWithWorkOS::class);
});

it('redirects unauthenticated visitors away from /admin/businesses', function () {
    $this->get('/admin/businesses')
        ->assertRedirect('/login');
});

it('returns 403 for a non-super-admin authenticated user', function () {
    $user = User::create([
        'name' => 'Member',
        'email' => 'member@example.com',
        'workos_id' => 'workos-1',
        'avatar' => '',
        'role' => UserRole::Member->value,
    ]);

    $this->actingAs($user)
        ->get('/admin/businesses')
        ->assertForbidden();
});

it('renders /admin/businesses for a super_admin', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'workos_id' => 'workos-2',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user)
        ->get('/admin/businesses')
        ->assertOk()
        ->assertSee('Businesses');
});

it('renders the dashboard for a super_admin (no auto-redirect)', function () {
    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin2@example.com',
        'workos_id' => 'workos-3',
        'avatar' => '',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
