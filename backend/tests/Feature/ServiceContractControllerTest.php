<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceContractControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_service_contracts(): void
    {
        $this->getJson('/api/v1/service-contracts')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_service_contracts(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);

        ServiceContract::factory()->count(2)->create(['elevator_id' => $elevator->id]);
        ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/service-contracts');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_view_a_single_service_contract(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-100',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/service-contracts/{$serviceContract->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $serviceContract->uuid)
            ->assertJsonPath('data.contract_number', 'CNT-100')
            ->assertJsonPath('data.elevator.uuid', $elevator->uuid);
    }

    public function test_viewing_another_companys_service_contract_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/service-contracts/{$otherServiceContract->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_service_contract(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/service-contracts', [
            'elevator_uuid' => $elevator->uuid,
            'contract_number' => 'CNT-200',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.contract_number', 'CNT-200')
            ->assertJsonPath('data.elevator.uuid', $elevator->uuid);

        $this->assertDatabaseHas('service_contracts', [
            'company_id' => $company->id,
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-200',
        ]);
    }

    public function test_creating_a_service_contract_ignores_client_supplied_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/service-contracts', [
            'company_id' => $otherCompany->id,
            'elevator_uuid' => $elevator->uuid,
            'contract_number' => 'CNT-300',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ])->assertCreated();

        $this->assertDatabaseHas('service_contracts', [
            'contract_number' => 'CNT-300',
            'company_id' => $company->id,
        ]);
    }

    public function test_creating_a_service_contract_requires_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/service-contracts', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['elevator_uuid', 'start_date', 'end_date']],
            ]);
    }

    public function test_creating_a_service_contract_with_another_companys_elevator_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/service-contracts', [
            'elevator_uuid' => $otherElevator->uuid,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['elevator_uuid']],
            ]);

        $this->assertDatabaseMissing('service_contracts', [
            'elevator_id' => $otherElevator->id,
        ]);
    }

    public function test_authenticated_user_can_update_their_own_service_contract(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/service-contracts/{$serviceContract->uuid}", [
            'status' => 'suspended',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('service_contracts', [
            'id' => $serviceContract->id,
            'status' => 'suspended',
        ]);
    }

    public function test_updating_another_companys_service_contract_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/service-contracts/{$otherServiceContract->uuid}", ['status' => 'terminated'])
            ->assertNotFound();
    }

    public function test_updating_a_service_contract_to_another_companys_elevator_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/service-contracts/{$serviceContract->uuid}", [
            'elevator_uuid' => $otherElevator->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('service_contracts', [
            'id' => $serviceContract->id,
            'elevator_id' => $elevator->id,
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_service_contract(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/service-contracts/{$serviceContract->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('service_contracts', [
            'id' => $serviceContract->id,
        ]);
    }

    public function test_deleting_another_companys_service_contract_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);
        $otherServiceContract = ServiceContract::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/service-contracts/{$otherServiceContract->uuid}")
            ->assertNotFound();
    }
}
