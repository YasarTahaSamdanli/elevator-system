<?php

namespace Database\Factories;

use App\Models\ServiceContract;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_contract_id' => ServiceContract::factory(),
            'type' => 'maintenance',
            'status' => 'draft',
            'priority' => 'normal',
            'scheduled_at' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'started_at' => null,
            'completed_at' => null,
            'assigned_user_id' => null,
            'description' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (WorkOrder $workOrder): void {
            $workOrder->company_id ??= ServiceContract::find($workOrder->service_contract_id)?->company_id;
        });
    }
}
