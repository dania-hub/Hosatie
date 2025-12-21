<?php

namespace Database\Factories;

use App\Models\PrescriptionDrug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PrescriptionDrug>
 */
class PrescriptionDrugFactory extends Factory
{
    protected $model = PrescriptionDrug::class;

    public function definition(): array
    {
        return [
            'monthly_quantity' => fake()->numberBetween(10, 150),
            'note' => fake()->optional(0.3)->sentence(),
        ];
    }
}
