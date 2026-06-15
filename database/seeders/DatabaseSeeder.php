<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Saloonify Admin',
            'email' => 'admin@saloonify.local',
            'role' => UserRole::SuperAdmin->value,
        ]);
    }
}
