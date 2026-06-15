<?php

declare(strict_types=1);

namespace Database\Factories\Documents;

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Enums\DocumentStatus;
use App\Modules\Documents\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $business = Business::factory();
        $customer = Customer::factory();
        $session = UploadSession::factory();

        $name = $this->faker->word().'.pdf';

        return [
            'business_id' => $business,
            'customer_id' => $customer,
            'upload_session_id' => $session,
            's3_key' => 'business/test/customer/test/session/test/'.$this->faker->uuid().'-'.$name,
            'original_name' => $name,
            'mime' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(1024, 5 * 1024 * 1024),
            'status' => DocumentStatus::Pending->value,
        ];
    }
}
