<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Material;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockMovement> */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'material_id' => Material::factory(['company_id' => $company]),
            'warehouse_id' => Warehouse::factory(['company_id' => $company]),
            'type' => 'purchase_in',
            'quantity' => fake()->randomFloat(3, 1, 20),
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'work_order_id' => null,
            'transfer_group_uuid' => null,
            'occurred_at' => now(),
            'created_by' => null,
            'note' => fake()->optional()->sentence(),
        ];
    }
}
