<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs_logs', function (Blueprint $table): void {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('business_id')->nullable()->constrained('businesses_businesses')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 64);
            $table->string('action', 120);
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 26)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('business_id');
            $table->index('actor_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs_logs');
    }
};
