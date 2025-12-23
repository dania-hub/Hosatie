<?php

namespace Database\Factories;

use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['active', 'cancelled', 'suspended']),
            'start_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'end_date' => fake()->boolean(60)
                ? fake()->dateTimeBetween('now', '+120 days')->format('Y-m-d')
                : null,
            'cancelled_at' => null,
        ];
    }
}
