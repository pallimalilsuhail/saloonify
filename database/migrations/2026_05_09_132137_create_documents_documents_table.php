<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_documents', function (Blueprint $table): void {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('business_id')->constrained('businesses_businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers_customers')->cascadeOnDelete();
            $table->foreignId('upload_session_id')->constrained('document_requests_upload_sessions')->cascadeOnDelete();
            $table->string('s3_key');
            $table->string('original_name');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('uploaded_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['upload_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_documents');
    }
};
