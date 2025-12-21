<?php

namespace Database\Factories;

use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'message' => fake()->paragraph(),
            'status' => fake()->randomElement(['قيد المراجعة', 'تمت المراجعة']),
            'replied_by' => null,
            'reply_message' => null,
            'replied_at' => null,
        ];
    }
}
