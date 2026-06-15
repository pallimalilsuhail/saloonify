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
            $table->foreignId('business_id')->nullable()->after('ulid')->constrained('businesses_businesses')->nullOnDelete();
            $table->string('role', 32)->default('member')->after('business_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['business_id']);
            $table->dropColumn(['ulid', 'business_id', 'role']);
        });
    }
};
