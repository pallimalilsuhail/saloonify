<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->ulid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('trn', 15);
            $table->char('country', 2)->default('AE');
            $table->char('currency', 3)->default('AED');
            $table->decimal('tax_rate', 5, 2)->default(5.00);
            $table->json('invoice_template_settings_json')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
