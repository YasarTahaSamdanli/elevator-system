<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkOrderItem> */
class WorkOrderItemFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'work_order_id' => WorkOrder::factory(['service_contract_id' => ServiceContract::factory()]),
            'material_id' => Material::factory(['company_id' => $company]),
            'quantity' => fake()->randomFloat(3, 1, 5),
            'unit_price' => fake()->randomFloat(2, 50, 500),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
