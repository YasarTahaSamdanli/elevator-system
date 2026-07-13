<?php

namespace Database\Factories;

use App\Models\Elevator;
use App\Models\ElevatorInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ElevatorInspection>
 */
class ElevatorInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inspectedAt = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'elevator_id' => Elevator::factory(),
            'type' => 'periodic',
            'inspection_body' => fake()->optional()->company(),
            'inspected_at' => $inspectedAt->format('Y-m-d'),
            'label' => fake()->randomElement(['green', 'blue', 'yellow', 'red']),
            'report_number' => fake()->optional()->bothify('RPT-######'),
            'next_inspection_date' => (clone $inspectedAt)->modify('+1 year')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ElevatorInspection $inspection): void {
            $inspection->company_id ??= Elevator::withoutGlobalScopes()->find($inspection->elevator_id)?->company_id;
        });
    }

    public function label(string $label): static
    {
        return $this->state(fn () => ['label' => $label]);
    }
}
