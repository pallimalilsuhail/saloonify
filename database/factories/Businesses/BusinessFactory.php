<?php

declare(strict_types=1);

namespace Database\Factories\Businesses;

use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Business>
 */
final class BusinessFactory extends Factory
{
    protected $model = Business::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => BusinessStatus::Active->value,
        ];
    }
}
