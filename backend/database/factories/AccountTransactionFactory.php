<?php

namespace Database\Factories;

use App\Models\AccountTransaction;
use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountTransaction>
 */
class AccountTransactionFactory extends Factory
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
            'type' => 'maintenance_fee',
            'amount' => fake()->randomFloat(2, 100, 5000),
            'occurred_at' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return $this
     */
    public function configure(): static
    {
        return $this->afterMaking(function (AccountTransaction $transaction): void {
            $transaction->company_id ??= Building::withoutGlobalScopes()
                ->find($transaction->building_id)?->company_id;
        });
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
