<?php

namespace Tests\Feature;

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

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_catalog_is_company_scoped_and_exposes_stock_on_hand(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id, 'code' => 'FREN-001']);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        Material::factory()->create(['company_id' => $otherCompany->id]);

        StockMovement::factory()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 10,
        ]);
        StockMovement::factory()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'transfer_out',
            'quantity' => 3,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/materials');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'FREN-001')
            ->assertJsonPath('data.0.stock_on_hand', '7');
    }

    public function test_authenticated_user_can_create_material_and_code_is_company_unique(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/materials', [
            'code' => 'HALAT-10',
            'name' => '10 mm Halat',
            'unit' => 'meter',
            'min_stock_level' => 25,
            'default_unit_price' => 120,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'HALAT-10');

        $this->assertDatabaseHas('materials', [
            'company_id' => $company->id,
            'code' => 'HALAT-10',
        ]);

        $this->actingAs($user)->postJson('/api/v1/materials', [
            'code' => 'HALAT-10',
            'name' => 'Tekrar',
            'unit' => 'meter',
        ])->assertStatus(422);
    }

    public function test_purchase_in_stock_movement_updates_default_price_when_requested(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id, 'default_unit_price' => 50]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/stock-movements', [
            'material_uuid' => $material->uuid,
            'warehouse_uuid' => $warehouse->uuid,
            'type' => 'purchase_in',
            'quantity' => 5,
            'unit_price' => 75,
            'update_material_price' => true,
            'note' => 'Mal kabul',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'purchase_in')
            ->assertJsonPath('data.created_by.uuid', $user->uuid);

        $this->assertDatabaseHas('stock_movements', [
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'default_unit_price' => 75,
        ]);
    }

    public function test_work_order_item_snapshots_material_price_without_stock_movement(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create(['service_contract_id' => $serviceContract->id]);
        $material = Material::factory()->create([
            'company_id' => $company->id,
            'default_unit_price' => 125,
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/work-orders/{$workOrder->uuid}/items", [
            'material_uuid' => $material->uuid,
            'quantity' => 2,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.material.uuid', $material->uuid)
            ->assertJsonPath('data.unit_price', '125.00');

        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => $workOrder->id,
            'material_id' => $material->id,
            'quantity' => 2,
            'unit_price' => 125,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_work_order_detail_includes_material_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create(['service_contract_id' => $serviceContract->id]);
        $material = Material::factory()->create(['company_id' => $company->id, 'name' => 'Kapı Fotoseli']);

        $workOrder->items()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/work-orders/{$workOrder->uuid}")
            ->assertOk()
            ->assertJsonPath('data.items.0.material.name', 'Kapı Fotoseli')
            ->assertJsonPath('data.items.0.unit_price', '450.00');
    }
    public function test_completing_work_order_creates_stock_out_movements_once(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id, 'type' => 'main']);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'status' => 'in_progress',
        ]);
        $material = Material::factory()->create(['company_id' => $company->id, 'default_unit_price' => 300]);

        StockMovement::factory()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 10,
            'unit_price' => 300,
        ]);
        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 2,
            'unit_price' => 300,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('stock_movements', [
            'work_order_id' => $workOrder->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'work_order_out',
            'quantity' => 2,
            'unit_price' => 300,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertSame(1, StockMovement::query()
            ->where('work_order_id', $workOrder->id)
            ->where('type', 'work_order_out')
            ->count());

        $this->actingAs($user)
            ->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonPath('data.0.stock_on_hand', '8');
    }

    public function test_adjustment_out_reduces_stock_on_hand(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        StockMovement::factory()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 10,
        ]);

        $this->actingAs($user)->postJson('/api/v1/stock-movements', [
            'material_uuid' => $material->uuid,
            'warehouse_uuid' => $warehouse->uuid,
            'type' => 'adjustment_out',
            'quantity' => 4,
            'note' => 'Sayım farkı',
        ])->assertCreated()
            ->assertJsonPath('data.signed_quantity', '-4');

        $this->actingAs($user)
            ->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonPath('data.0.stock_on_hand', '6');
    }

    public function test_transfer_types_are_rejected_on_plain_store_endpoint(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        foreach (['transfer_in', 'transfer_out'] as $type) {
            $this->actingAs($user)->postJson('/api/v1/stock-movements', [
                'material_uuid' => $material->uuid,
                'warehouse_uuid' => $warehouse->uuid,
                'type' => $type,
                'quantity' => 1,
            ])->assertStatus(422)
                ->assertJsonStructure(['error' => ['details' => ['type']]]);
        }
    }

    public function test_transfer_endpoint_creates_both_legs_atomically(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id]);
        $mainWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'type' => 'main']);
        $vehicleWarehouse = Warehouse::factory()->create(['company_id' => $company->id, 'type' => 'vehicle']);

        $response = $this->actingAs($user)->postJson('/api/v1/stock-movements/transfers', [
            'material_uuid' => $material->uuid,
            'from_warehouse_uuid' => $mainWarehouse->uuid,
            'to_warehouse_uuid' => $vehicleWarehouse->uuid,
            'quantity' => 3,
            'note' => 'Araca zimmet',
        ]);

        $response->assertCreated()->assertJsonCount(2, 'data');

        $movements = StockMovement::query()->orderBy('type')->get();
        $this->assertCount(2, $movements);
        $this->assertSame(['transfer_in', 'transfer_out'], $movements->pluck('type')->all());
        $this->assertSame(1, $movements->pluck('transfer_group_uuid')->unique()->count());
        $this->assertNotNull($movements->first()->transfer_group_uuid);
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_out', 'warehouse_id' => $mainWarehouse->id]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'transfer_in', 'warehouse_id' => $vehicleWarehouse->id]);

        // Net stock is unchanged by a transfer.
        $this->actingAs($user)
            ->getJson('/api/v1/materials')
            ->assertOk()
            ->assertJsonPath('data.0.stock_on_hand', '0');
    }

    public function test_transfer_to_same_warehouse_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $material = Material::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/stock-movements/transfers', [
            'material_uuid' => $material->uuid,
            'from_warehouse_uuid' => $warehouse->uuid,
            'to_warehouse_uuid' => $warehouse->uuid,
            'quantity' => 1,
        ])->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['from_warehouse_uuid']]]);
    }

    public function test_items_cannot_be_modified_on_a_completed_work_order(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'status' => 'completed',
        ]);
        $material = Material::factory()->create(['company_id' => $company->id]);
        $item = $workOrder->items()->create([
            'company_id' => $company->id,
            'material_id' => $material->id,
            'quantity' => 1,
            'unit_price' => 100,
        ]);

        $this->actingAs($user)->postJson("/api/v1/work-orders/{$workOrder->uuid}/items", [
            'material_uuid' => $material->uuid,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->actingAs($user)->patchJson("/api/v1/work-orders/{$workOrder->uuid}/items/{$item->uuid}", [
            'quantity' => 5,
        ])->assertStatus(422);

        $this->actingAs($user)->deleteJson("/api/v1/work-orders/{$workOrder->uuid}/items/{$item->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('work_order_items', [
            'id' => $item->id,
            'quantity' => 1,
            'deleted_at' => null,
        ]);
    }

    public function test_completion_uses_assigned_technicians_vehicle_warehouse_when_available(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $technician = User::factory()->create(['company_id' => $company->id]);
        $vehicleWarehouse = Warehouse::factory()->create([
            'company_id' => $company->id,
            'type' => 'vehicle',
            'user_id' => $technician->id,
        ]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'assigned_user_id' => $technician->id,
            'status' => 'in_progress',
        ]);
        $material = Material::factory()->create(['company_id' => $company->id]);

        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 1,
            'unit_price' => 450,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'work_order_id' => $workOrder->id,
            'warehouse_id' => $vehicleWarehouse->id,
            'type' => 'work_order_out',
            'quantity' => 1,
        ]);
    }
}
