<?php

declare(strict_types=1);

use App\Modules\Businesses\Http\Requests\OnboardBusinessRequest;

test('onboard rules require the expected fields', function (): void {
    $rules = OnboardBusinessRequest::create('/admin/businesses', 'POST', [
        'admin' => ['login' => 'olivia@glow.test'],
    ])->rules();

    expect($rules)->toHaveKeys(['name', 'trn', 'admin.name', 'admin.login', 'admin.password']);
    expect($rules['name'])->toContain('required');
    expect($rules['trn'])->toContain('required')->toContain('digits:15');
    expect($rules['admin.password'])->toContain('min:8');
});

test('login rule validates an email when an email is supplied', function (): void {
    $rules = OnboardBusinessRequest::create('/admin/businesses', 'POST', [
        'admin' => ['login' => 'olivia@glow.test'],
    ])->rules();

    expect($rules['admin.login'])->toContain('email');
});

test('login rule validates a username when no email is supplied', function (): void {
    $rules = OnboardBusinessRequest::create('/admin/businesses', 'POST', [
        'admin' => ['login' => 'olivia'],
    ])->rules();

    expect($rules['admin.login'])->not->toContain('email');
});
