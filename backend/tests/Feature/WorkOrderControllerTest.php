<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Elevator;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\DefaultRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_work_orders(): void
    {
        $this->getJson('/api/v1/work-orders')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_work_orders(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        WorkOrder::factory()->count(2)->create(['service_contract_id' => $serviceContract->id]);
        WorkOrder::factory()->create(['service_contract_id' => $otherServiceContract->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/work-orders');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_work_orders_can_be_filtered_by_elevator_uuid(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $company->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $workOrder = WorkOrder::factory()->create(['service_contract_id' => $serviceContract->id]);
        WorkOrder::factory()->create(['service_contract_id' => $otherServiceContract->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/work-orders?filter[elevator_uuid]='.$elevator->uuid)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $workOrder->uuid);
    }

    public function test_authenticated_user_can_view_a_single_work_order(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'type' => 'fault',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/work-orders/{$workOrder->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $workOrder->uuid)
            ->assertJsonPath('data.type', 'fault')
            ->assertJsonPath('data.work_order_number', $workOrder->work_order_number)
            ->assertJsonPath('data.service_contract.uuid', $serviceContract->uuid);
    }

    public function test_viewing_another_companys_work_order_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);
        $otherWorkOrder = WorkOrder::factory()->create(['service_contract_id' => $otherServiceContract->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/work-orders/{$otherWorkOrder->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_work_order(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $serviceContract->uuid,
            'type' => 'maintenance',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'maintenance')
            ->assertJsonPath('data.service_contract.uuid', $serviceContract->uuid);

        $this->assertDatabaseHas('work_orders', [
            'company_id' => $company->id,
            'service_contract_id' => $serviceContract->id,
            'type' => 'maintenance',
        ]);
    }

    public function test_authenticated_user_can_create_a_work_order_with_assigned_technician(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $technician = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $serviceContract->uuid,
            'type' => 'repair',
            'assigned_user_uuid' => $technician->uuid,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.assigned_user.uuid', $technician->uuid);

        $this->assertDatabaseHas('work_orders', [
            'service_contract_id' => $serviceContract->id,
            'assigned_user_id' => $technician->id,
        ]);
    }

    public function test_authenticated_user_can_create_a_work_order_with_material_items(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $material = Material::factory()->create([
            'company_id' => $company->id,
            'default_unit_price' => 125,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $serviceContract->uuid,
            'type' => 'maintenance',
            'items' => [
                [
                    'material_uuid' => $material->uuid,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.items.0.material.uuid', $material->uuid)
            ->assertJsonPath('data.items.0.quantity', '2.000');

        $this->assertDatabaseHas('work_order_items', [
            'company_id' => $company->id,
            'material_id' => $material->id,
            'quantity' => '2.000',
            'unit_price' => '125.00',
        ]);
    }

    public function test_creating_a_work_order_ignores_client_supplied_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'company_id' => $otherCompany->id,
            'service_contract_uuid' => $serviceContract->uuid,
            'type' => 'maintenance',
        ])->assertCreated();

        $this->assertDatabaseHas('work_orders', [
            'service_contract_id' => $serviceContract->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_creating_a_work_order_requires_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['service_contract_uuid', 'type']],
            ]);
    }

    public function test_creating_a_work_order_with_another_companys_service_contract_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $otherServiceContract->uuid,
            'type' => 'maintenance',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['service_contract_uuid']],
            ]);

        $this->assertDatabaseMissing('work_orders', [
            'service_contract_id' => $otherServiceContract->id,
        ]);
    }

    public function test_creating_a_work_order_with_another_companys_technician_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherTechnician = User::factory()->create(['company_id' => $otherCompany->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $serviceContract->uuid,
            'type' => 'maintenance',
            'assigned_user_uuid' => $otherTechnician->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['assigned_user_uuid']],
            ]);
    }

    public function test_authenticated_user_can_update_their_own_work_order(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/work-orders/{$workOrder->uuid}", [
            'status' => 'planned',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'planned');

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => 'planned',
        ]);
    }

    public function test_updating_another_companys_work_order_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);
        $otherWorkOrder = WorkOrder::factory()->create(['service_contract_id' => $otherServiceContract->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$otherWorkOrder->uuid}", ['status' => 'cancelled'])
            ->assertNotFound();
    }

    public function test_updating_a_work_order_to_another_companys_service_contract_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);
        $workOrder = WorkOrder::factory()->create(['service_contract_id' => $serviceContract->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/work-orders/{$workOrder->uuid}", [
            'service_contract_uuid' => $otherServiceContract->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'service_contract_id' => $serviceContract->id,
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_work_order(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create(['service_contract_id' => $serviceContract->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/work-orders/{$workOrder->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('work_orders', [
            'id' => $workOrder->id,
        ]);
    }

    public function test_technician_cannot_start_a_work_order_without_scanning_the_elevator_qr(): void
    {
        [$technician, $workOrder] = $this->createAssignedWorkOrderWithTechnician();

        $this->actingAs($technician)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['qr_identifier']]]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => 'assigned',
        ]);
    }

    public function test_technician_cannot_start_a_work_order_with_another_elevators_qr(): void
    {
        [$technician, $workOrder, $company] = $this->createAssignedWorkOrderWithTechnician();
        $otherElevator = Elevator::factory()->create(['company_id' => $company->id]);

        $this->actingAs($technician)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", [
                'status' => 'in_progress',
                'qr_identifier' => $otherElevator->qr_identifier,
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error' => ['details' => ['qr_identifier']]]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => 'assigned',
        ]);
    }

    public function test_technician_can_start_a_work_order_by_scanning_the_matching_elevator_qr(): void
    {
        [$technician, $workOrder] = $this->createAssignedWorkOrderWithTechnician();

        $this->actingAs($technician)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", [
                'status' => 'in_progress',
                'qr_identifier' => $workOrder->serviceContract->elevator->qr_identifier,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertNotNull($workOrder->fresh()->started_at);
    }

    public function test_technician_can_complete_an_in_progress_work_order_without_qr(): void
    {
        [$technician, $workOrder] = $this->createAssignedWorkOrderWithTechnician(status: 'in_progress');

        $this->actingAs($technician)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_non_technician_can_start_a_work_order_without_qr(): void
    {
        [, $workOrder, $company] = $this->createAssignedWorkOrderWithTechnician();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->syncRoles(['Manager']);

        $this->actingAs($manager)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_starting_technician_takes_over_the_assignment(): void
    {
        [, $workOrder, $company] = $this->createAssignedWorkOrderWithTechnician();
        $otherTechnician = User::factory()->create(['company_id' => $company->id]);
        $otherTechnician->syncRoles(['Technician']);

        $this->actingAs($otherTechnician)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", [
                'status' => 'in_progress',
                'qr_identifier' => $workOrder->serviceContract->elevator->qr_identifier,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.uuid', $otherTechnician->uuid);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'assigned_user_id' => $otherTechnician->id,
        ]);
    }

    public function test_non_technician_start_keeps_the_existing_assignment(): void
    {
        [$technician, $workOrder, $company] = $this->createAssignedWorkOrderWithTechnician();
        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->syncRoles(['Manager']);

        $this->actingAs($manager)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.assigned_user.uuid', $technician->uuid);
    }

    /**
     * @return array{0: User, 1: WorkOrder, 2: Company}
     */
    private function createAssignedWorkOrderWithTechnician(string $status = 'assigned'): array
    {
        $this->seed(DefaultRoleSeeder::class);

        $company = Company::factory()->create();
        $technician = User::factory()->create(['company_id' => $company->id]);
        $technician->syncRoles(['Technician']);

        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'status' => $status,
            'assigned_user_id' => $technician->id,
            'started_at' => $status === 'in_progress' ? now() : null,
        ]);

        return [$technician, $workOrder, $company];
    }

    public function test_deleting_another_companys_work_order_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);
        $otherWorkOrder = WorkOrder::factory()->create(['service_contract_id' => $otherServiceContract->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/work-orders/{$otherWorkOrder->uuid}")
            ->assertNotFound();
    }
}
