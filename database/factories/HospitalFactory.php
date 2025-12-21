<?php

namespace Database\Factories;

use App\Models\Hospital;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hospital>
 */
class HospitalFactory extends Factory
{
    protected $model = Hospital::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' ' . fake()->randomElement(['المستشفى', 'المركز الصحي', 'العيادة']),
            'code' => fake()->unique()->bothify('HSP-??-####'),
            'type' => fake()->randomElement(['hospital', 'health_center', 'clinic']),
            'city' => fake()->randomElement(['طرابلس', 'بنغازي']),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
