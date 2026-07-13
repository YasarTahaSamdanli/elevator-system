<?php

namespace Database\Factories;

use App\Models\ElevatorInspection;
use App\Models\InspectionFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionFinding>
 */
class InspectionFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'elevator_inspection_id' => ElevatorInspection::factory(),
            'description' => fake()->sentence(),
            'is_resolved' => false,
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (InspectionFinding $finding): void {
            $finding->company_id ??= ElevatorInspection::withoutGlobalScopes()->find($finding->elevator_inspection_id)?->company_id;
        });
    }
}
