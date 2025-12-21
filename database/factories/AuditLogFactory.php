<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'table_name' => fake()->randomElement(['users', 'hospital', 'prescription', 'inventory']),
            'record_id' => fake()->numberBetween(1, 5000),
            'old_values' => null,
            'new_values' => null,
            'ip_address' => fake()->ipv4(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
