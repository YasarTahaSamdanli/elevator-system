<?php

namespace Database\Factories;

use App\Models\Elevator;
use App\Models\ServiceContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceContract>
 */
class ServiceContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 year', '+1 month');

        return [
            'elevator_id' => Elevator::factory(),
            'contract_number' => fake()->optional()->bothify('CNT-####'),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => fake()->dateTimeBetween($startDate, '+2 years')->format('Y-m-d'),
            'status' => 'active',
            'monthly_fee' => fake()->optional()->randomFloat(2, 1000, 25000),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ServiceContract $serviceContract): void {
            $serviceContract->company_id ??= Elevator::find($serviceContract->elevator_id)?->company_id;
        });
    }
}
