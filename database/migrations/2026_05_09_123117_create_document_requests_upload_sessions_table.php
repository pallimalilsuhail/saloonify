<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_requests_upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('business_id')->constrained('businesses_businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('max_files');
            $table->unsignedInteger('max_bytes');
            $table->json('allowed_mime');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_requests_upload_sessions');
    }
};
