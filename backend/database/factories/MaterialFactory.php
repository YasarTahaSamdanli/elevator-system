<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Material> */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => fake()->unique()->bothify('MAT-###'),
            'name' => fake()->words(3, true),
            'unit' => 'piece',
            'category' => fake()->optional()->randomElement(['Elektrik', 'Mekanik', 'Güvenlik']),
            'min_stock_level' => fake()->numberBetween(0, 10),
            'default_unit_price' => fake()->randomFloat(2, 50, 5000),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
