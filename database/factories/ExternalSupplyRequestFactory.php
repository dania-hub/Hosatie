<?php

namespace Database\Factories;

use App\Models\ExternalSupplyRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalSupplyRequest>
 */
class ExternalSupplyRequestFactory extends Factory
{
    protected $model = ExternalSupplyRequest::class;

    public function definition(): array
    {
        return [
            'supplier_id' => null,
            'requested_by' => null,
            'handeled_by' => null,
            'handeled_at' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'fulfilled', 'rejected']),
        ];
    }
}
