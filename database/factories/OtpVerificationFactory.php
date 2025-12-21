<?php

namespace Database\Factories;

use App\Models\OtpVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtpVerification>
 */
class OtpVerificationFactory extends Factory
{
    protected $model = OtpVerification::class;

    public function definition(): array
    {
        return [
            'phone' => fake()->phoneNumber(),
            'otp' => fake()->numerify('####'),
            'expires_at' => fake()->dateTimeBetween('now', '+3 days'),
        ];
    }
}
