<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->bothify('SUP-??-#####'),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'city' => fake()->randomElement(['طرابلس', 'بنغازي']),
            'status' => fake()->randomElement(['active', 'inactive']),
        ];
    }
}
