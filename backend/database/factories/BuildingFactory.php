<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Building>
 */
class BuildingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->company().' Plaza',
            'code' => fake()->optional()->bothify('BLD-###'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'district' => fake()->citySuffix(),
            'manager_name' => fake()->optional()->name(),
            'manager_phone' => fake()->optional()->phoneNumber(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
