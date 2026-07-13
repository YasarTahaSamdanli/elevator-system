<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
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
            'name' => fake()->unique()->randomElement(['Banka GR', 'Banka Resmi', 'Elden Ödeme']).' '.fake()->numberBetween(1, 999),
            'is_active' => true,
        ];
    }
}
