<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
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
