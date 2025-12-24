<?php

namespace Database\Factories;

use App\Models\PatientTransferRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientTransferRequest>
 */
class PatientTransferRequestFactory extends Factory
{
    protected $model = PatientTransferRequest::class;

    public function definition(): array
    {
        return [
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'reason' => fake()->optional(0.7)->sentence(),
            'handeled_by' => null,
            'handeled_at' => null,
            'rejection_reason' => null,
        ];
    }
}
