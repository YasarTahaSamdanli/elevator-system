<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_the_aggregated_payload(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create([
            'company_id' => $company->id,
            'building_id' => $building->id,
        ]);
        $contract = ServiceContract::factory()->create([
            'company_id' => $company->id,
            'elevator_id' => $elevator->id,
            'status' => 'active',
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        $workOrder = WorkOrder::factory()->create([
            'company_id' => $company->id,
            'service_contract_id' => $contract->id,
            'status' => 'assigned',
            'priority' => 'high',
            'scheduled_at' => now(),
        ]);
        $material = Material::factory()->create([
            'company_id' => $company->id,
            'min_stock_level' => 10,
            'default_unit_price' => 100,
        ]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        StockMovement::factory()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 3,
            'unit_price' => 100,
            'work_order_id' => $workOrder->id,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stats.activeElevators', 1)
            ->assertJsonPath('data.stats.openWorkOrders', 1)
            ->assertJsonStructure([
                'data' => [
                    'stats',
                    'operations',
                    'volume',
                    'inventoryMovement',
                    'distribution',
                    'openWorkOrders',
                    'activity',
                    'lowStockMaterials',
                    'topConsumedMaterials',
                    'recentStockMovements',
                ],
            ]);
    }
}
