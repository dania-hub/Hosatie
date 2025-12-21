<?php

namespace Database\Factories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory>
 */
class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'current_quantity' => fake()->numberBetween(10, 500),
            'minimum_level' => fake()->numberBetween(5, 150),
            'supplier_id' => null,
            'warehouse_id' => null,
            'pharmacy_id' => null,
        ];
    }
}
