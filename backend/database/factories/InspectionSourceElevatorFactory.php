<?php

namespace Database\Factories;

use App\Models\Elevator;
use App\Models\InspectionSourceElevator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionSourceElevator>
 */
class InspectionSourceElevatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'elevator_id' => Elevator::factory(),
            'source' => 'royalcert',
            'external_key' => mb_strtoupper(fake()->streetName(), 'UTF-8'),
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (InspectionSourceElevator $mapping): void {
            $mapping->company_id ??= Elevator::withoutGlobalScopes()->find($mapping->elevator_id)?->company_id;
        });
    }
}
