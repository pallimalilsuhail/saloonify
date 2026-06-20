<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\DTOs\OnboardedBusiness;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\OnboardBusiness\OnboardBusiness;
use App\Modules\Businesses\UseCases\OnboardBusiness\OnboardBusinessHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Exercises the handler directly (no Mediator, no HTTP) — just handle() against the DB.
uses(RefreshDatabase::class);

test('handle() creates a business + active admin and returns the DTO for an email login', function (): void {
    $result = (new OnboardBusinessHandler)->handle(new OnboardBusiness(
        name: 'Glow Salon',
        trn: str_repeat('9', 15),
        adminName: 'Olivia',
        login: 'olivia@glow.test',
        password: 'password123',
    ));

    expect($result)->toBeInstanceOf(OnboardedBusiness::class)
        ->and($result->login)->toBe('olivia@glow.test');

    // DTO carries the pre-generated id — and it matches the persisted row.
    $business = Business::firstOrFail();
    expect($business->ulid)->toBe($result->businessId->toString());

    $admin = User::where('email', 'olivia@glow.test')->firstOrFail();
    expect($admin->role)->toBe(UserRole::BusinessAdmin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->business_id)->toBe($business->id)
        ->and($admin->username)->toBeNull();
});

test('handle() generates a synthetic email for a username-only admin', function (): void {
    $result = (new OnboardBusinessHandler)->handle(new OnboardBusiness(
        name: 'Glow Salon',
        trn: str_repeat('9', 15),
        adminName: 'Ali',
        login: 'ali',
        password: 'password123',
    ));

    $admin = User::where('username', 'ali')->firstOrFail();
    expect($admin->email)->toBe('ali@glow-salon.saloonify.local')
        ->and($admin->business_id)->not->toBeNull()
        ->and($result->login)->toBe('ali');
});
