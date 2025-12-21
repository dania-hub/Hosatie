<?php

namespace Database\Factories;

use App\Models\Dispensing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Dispensing>
 */
class DispensingFactory extends Factory
{
    protected $model = Dispensing::class;

    public function definition(): array
    {
        return [
            'quantity_dispensed' => fake()->numberBetween(5, 100),
            'dispense_month' => fake()->dateTimeBetween('-12 months', 'now')->format('Y-m-d'),
            'reverted' => fake()->boolean(15),
            'reverted_at' => null,
            'reverted_by' => null,
        ];
    }
}
