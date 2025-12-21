<?php

namespace Database\Factories;

use App\Models\InternalSupplyRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InternalSupplyRequestItem>
 */
class InternalSupplyRequestItemFactory extends Factory
{
    protected $model = InternalSupplyRequestItem::class;

    public function definition(): array
    {
        return [
            'requested_qty' => fake()->numberBetween(10, 200),
            'approved_qty' => null,
            'fulfilled_qty' => null,
        ];
    }
}
