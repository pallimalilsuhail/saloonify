<?php

declare(strict_types=1);

namespace Database\Factories\Customers;

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'name' => $this->faker->name(),
            // UAE mobile: +9715[0/2/4/5/6/8] + 7 digits. Pick a valid prefix
            // so libphonenumber accepts the result downstream.
            'phone' => '+97150'.$this->faker->numerify('#######'),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
