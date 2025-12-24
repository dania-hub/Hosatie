<?php

namespace Database\Factories;

use App\Models\InternalSupplyRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InternalSupplyRequest>
 */
class InternalSupplyRequestFactory extends Factory
{
    protected $model = InternalSupplyRequest::class;

    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'fulfilled', 'cancelled']),
            'handeled_by' => null,
            'handeled_at' => null,
        ];
    }
}
