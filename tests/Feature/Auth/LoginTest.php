<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Staff\Support\SyntheticEmail;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

function loginUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => UserRole::SuperAdmin->value,
        'password' => Hash::make('secret-pass'),
    ], $attributes));
}

test('login screen can be rendered', function (): void {
    $this->get(route('login'))->assertOk();
});

test('user can authenticate with email', function (): void {
    $user = loginUser(['email' => 'owner@glow.test']);

    Volt::test('auth.login')
        ->set('login', 'owner@glow.test')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('user can authenticate with username', function (): void {
    $user = loginUser(['username' => 'sara.k']);

    Volt::test('auth.login')
        ->set('login', 'sara.k')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('emailless staff (synthetic email) can authenticate with username', function (): void {
    $user = loginUser([
        'username' => 'ali',
        'email' => SyntheticEmail::make('ali', 'glow-salon'),
    ]);

    expect($user->email)->toBe('ali@glow-salon.saloonify.local');

    Volt::test('auth.login')
        ->set('login', 'ali')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('user cannot authenticate with an invalid password', function (): void {
    $user = loginUser(['email' => 'x@glow.test']);

    Volt::test('auth.login')
        ->set('login', 'x@glow.test')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('login');

    $this->assertGuest();
});

test('terminated user is blocked', function (): void {
    loginUser(['email' => 'gone@glow.test', 'status' => UserStatus::Terminated->value]);

    Volt::test('auth.login')
        ->set('login', 'gone@glow.test')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasErrors('login');

    $this->assertGuest();
});

test('on_leave user can still log in', function (): void {
    $user = loginUser(['email' => 'leave@glow.test', 'status' => UserStatus::OnLeave->value]);

    Volt::test('auth.login')
        ->set('login', 'leave@glow.test')
        ->set('password', 'secret-pass')
        ->call('login')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('authenticated user can log out', function (): void {
    $user = loginUser();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
