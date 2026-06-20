<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Staff\Exceptions\StaffPolicyException;
use App\Modules\Staff\UseCases\UpdateStaff\UpdateStaff;
use AvoqadoDev\UseCase\Facades\Mediator;

function staffUser(Business $business, UserRole $role, UserStatus $status = UserStatus::Active): User
{
    return User::factory()->create([
        'business_id' => $business->id,
        'role' => $role->value,
        'status' => $status->value,
    ]);
}

test('an agent can be put on leave and then terminated', function (): void {
    $business = Business::factory()->create();
    staffUser($business, UserRole::BusinessAdmin); // keep an admin around
    $agent = staffUser($business, UserRole::LocationAgent);

    Mediator::dispatch(new UpdateStaff(userId: $agent->id, status: UserStatus::OnLeave));
    expect($agent->fresh()->status)->toBe(UserStatus::OnLeave);

    Mediator::dispatch(new UpdateStaff(userId: $agent->id, status: UserStatus::Terminated));
    expect($agent->fresh()->status)->toBe(UserStatus::Terminated);
});

test('a terminated user cannot be modified', function (): void {
    $business = Business::factory()->create();
    staffUser($business, UserRole::BusinessAdmin);
    $agent = staffUser($business, UserRole::LocationAgent, UserStatus::Terminated);

    Mediator::dispatch(new UpdateStaff(userId: $agent->id, name: 'New Name'));
})->throws(StaffPolicyException::class);

test('the last active business admin cannot be demoted', function (): void {
    $business = Business::factory()->create();
    $admin = staffUser($business, UserRole::BusinessAdmin);

    Mediator::dispatch(new UpdateStaff(userId: $admin->id, status: UserStatus::OnLeave));
})->throws(StaffPolicyException::class);

test('an admin can be put on leave when another active admin exists', function (): void {
    $business = Business::factory()->create();
    $admin = staffUser($business, UserRole::BusinessAdmin);
    staffUser($business, UserRole::BusinessAdmin); // second active admin

    Mediator::dispatch(new UpdateStaff(userId: $admin->id, status: UserStatus::OnLeave));

    expect($admin->fresh()->status)->toBe(UserStatus::OnLeave);
});

test('demoting to location agent without a location is rejected', function (): void {
    $business = Business::factory()->create();
    staffUser($business, UserRole::BusinessAdmin); // keep a last admin
    $admin = staffUser($business, UserRole::BusinessAdmin);

    Mediator::dispatch(new UpdateStaff(userId: $admin->id, role: UserRole::LocationAgent, locationIds: []));
})->throws(StaffPolicyException::class);
