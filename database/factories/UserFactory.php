<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'national_id' => fake()->unique()->numerify('2##########'),
            'birth_date' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'type' => 'data_entry',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->phoneNumber(),
            'full_name' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'fcm_token' => Str::random(64),
            'warehouse_id' => null,
            'hospital_id' => null,
            'supplier_id' => null,
            'pharmacy_id' => null,
            'status' => fake()->randomElement(['active','inactive','pending_activation']),
            'created_by' => null,
        ];
    }

    public function patient(): static
    {
        return $this->state(fn () => [
            'type' => 'patient',
            'status' => 'active',
        ]);
    }

    public function doctor(): static
    {
        return $this->state(fn () => [
            'type' => 'doctor',
            'status' => 'active',
        ]);
    }

    public function pharmacist(): static
    {
        return $this->state(fn () => [
            'type' => 'pharmacist',
            'status' => 'active',
        ]);
    }

    public function warehouseManager(): static
    {
        return $this->state(fn () => [
            'type' => 'warehouse_manager',
            'status' => 'active',
        ]);
    }

    public function departmentHead(): static
    {
        return $this->state(fn () => [
            'type' => 'department_head',
            'status' => 'active',
        ]);
    }

    public function hospitalAdmin(): static
    {
        return $this->state(fn () => [
            'type' => 'hospital_admin',
            'status' => 'active',
        ]);
    }

    public function supplierAdmin(): static
    {
        return $this->state(fn () => [
            'type' => 'supplier_admin',
            'status' => 'active',
        ]);
    }

    public function dataEntry(): static
    {
        return $this->state(fn () => [
            'type' => 'data_entry',
            'status' => 'active',
        ]);
    }
}
