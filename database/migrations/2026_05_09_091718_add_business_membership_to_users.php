<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->ulid()->unique()->after('id');
            $table->foreignId('business_id')->nullable()->after('ulid')->constrained('businesses')->nullOnDelete();
            $table->string('role', 32)->default('location_agent')->after('business_id');
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('pin_hash')->nullable()->after('password');
            $table->string('status', 16)->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['business_id']);
            $table->dropColumn(['ulid', 'business_id', 'role', 'username', 'pin_hash', 'status']);
        });
    }
};
