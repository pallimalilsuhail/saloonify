<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\DTOs\OnboardedBusiness;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\OnboardBusiness\OnboardBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;

test('OnboardBusiness creates a business + active business-admin and returns a DTO', function (): void {
    $result = Mediator::dispatch(new OnboardBusiness(
        name: 'Glow Salon',
        trn: str_repeat('9', 15),
        adminName: 'Olivia',
        login: 'olivia@glow.test',
        password: 'password123',
    ));

    expect($result)->toBeInstanceOf(OnboardedBusiness::class)
        ->and($result->login)->toBe('olivia@glow.test');

    $business = Business::where('name', 'Glow Salon')->firstOrFail();
    expect($business->ulid)->toBe($result->businessId->toString())
        ->and($business->country)->toBe('AE')
        ->and($business->currency)->toBe('AED')
        ->and($business->tax_rate)->toBe('5.00');

    $admin = User::where('email', 'olivia@glow.test')->firstOrFail();
    expect($admin->role)->toBe(UserRole::BusinessAdmin)
        ->and($admin->status)->toBe(UserStatus::Active)
        ->and($admin->business_id)->toBe($business->id);
});

test('OnboardBusiness generates a synthetic email for a username-only admin', function (): void {
    $result = Mediator::dispatch(new OnboardBusiness(
        name: 'Glow Salon',
        trn: str_repeat('9', 15),
        adminName: 'Ali',
        login: 'ali',
        password: 'password123',
    ));

    $admin = User::where('username', 'ali')->firstOrFail();
    expect($admin->email)->toEndWith('.saloonify.local')
        ->and($admin->email)->toStartWith('ali@')
        ->and($result->login)->toBe('ali');
});
