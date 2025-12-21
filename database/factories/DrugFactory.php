<?php

namespace Database\Factories;

use App\Models\Drug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Drug>
 */
class DrugFactory extends Factory
{
    protected $model = Drug::class;

    public function definition(): array
    {
        $forms = ['tablet', 'capsule', 'syrup', 'injection', 'cream'];
        $categories = ['antibiotic', 'analgesic', 'antipyretic', 'antiviral', 'antidepressant'];
        $units = ['mg', 'ml', 'pcs'];
        $utilizationTypes = ['chronic', 'acute', 'emergency'];

        return [
            'name' => 'دواء ' . fake()->unique()->bothify('??###'),
            'generic_name' => fake()->word() . ' generic',
            'strength' => fake()->randomElement(['50mg', '100mg', '250mg', '500mg']),
            'form' => fake()->randomElement($forms),
            'category' => fake()->randomElement($categories),
            'unit' => fake()->randomElement($units),
            'max_monthly_dose' => fake()->numberBetween(20, 120),
            'status' => fake()->randomElement(['متوفر', 'غير متوفر', 'تم الصرف']),
            'manufacturer' => fake()->company(),
            'country' => fake()->country(),
            'utilization_type' => fake()->randomElement($utilizationTypes),
            'warnings' => fake()->sentence(),
            'indications' => fake()->sentence(),
            'contraindications' => fake()->sentence(),
            'expiry_date' => fake()->dateTimeBetween('now', '+4 years')->format('Y-m-d'),
        ];
    }
}
