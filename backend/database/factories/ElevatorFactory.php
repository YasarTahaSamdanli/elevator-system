<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\Elevator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Elevator>
 */
class ElevatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'serial_number' => fake()->bothify('ELV-####'),
            'name' => fake()->optional()->words(2, true),
            'manufacturer' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('MDL-###'),
            'installation_year' => fake()->optional()->numberBetween(1980, (int) date('Y')),
            'capacity_kg' => fake()->optional()->numberBetween(320, 2500),
            'person_capacity' => fake()->optional()->numberBetween(4, 33),
            'stop_count' => fake()->optional()->numberBetween(2, 60),
            'registration_number' => fake()->optional()->bothify('REG-####'),
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
