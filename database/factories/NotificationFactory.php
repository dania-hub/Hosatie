<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'type' => fake()->randomElement(['عادي', 'مستعجل']),
            'is_read' => fake()->boolean(60),
        ];
    }
}
