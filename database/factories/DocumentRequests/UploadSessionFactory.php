<?php

declare(strict_types=1);

namespace Database\Factories\DocumentRequests;

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Shared\ValueObjects\Token;

/**
 * @extends Factory<UploadSession>
 */
final class UploadSessionFactory extends Factory
{
    protected $model = UploadSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'token_hash' => Token::generate()->hash(),
            'status' => UploadSessionStatus::Active->value,
            'max_files' => 20,
            'max_bytes' => 25 * 1024 * 1024,
            'allowed_mime' => ['application/pdf', 'image/jpeg', 'image/png', 'image/heic'],
            'expires_at' => now()->addHour(),
        ];
    }
}
