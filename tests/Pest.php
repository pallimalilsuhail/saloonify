<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

pest()->use(RefreshDatabase::class)->in('Feature');

function superAdminUser(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin->value]);
}
