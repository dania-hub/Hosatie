<?php

namespace Database\Factories;

use App\Models\ExternalSupplyRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalSupplyRequestItem>
 */
class ExternalSupplyRequestItemFactory extends Factory
{
    protected $model = ExternalSupplyRequestItem::class;

    public function definition(): array
    {
        return [
            'requested_qty' => fake()->numberBetween(20, 250),
            'approved_qty' => null,
            'fulfilled_qty' => null,
        ];
    }
}
