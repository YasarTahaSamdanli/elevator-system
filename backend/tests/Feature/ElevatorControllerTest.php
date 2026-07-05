<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElevatorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_elevators(): void
    {
        $this->getJson('/api/v1/elevators')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_elevators(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);

        Elevator::factory()->count(2)->create(['building_id' => $building->id]);
        Elevator::factory()->create(['building_id' => $otherBuilding->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/elevators');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_view_a_single_elevator(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create([
            'building_id' => $building->id,
            'serial_number' => 'SN-100',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/elevators/{$elevator->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $elevator->uuid)
            ->assertJsonPath('data.serial_number', 'SN-100')
            ->assertJsonPath('data.building.uuid', $building->uuid);
    }

    public function test_viewing_another_companys_elevator_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);
        $otherElevator = Elevator::factory()->create(['building_id' => $otherBuilding->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/elevators/{$otherElevator->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_an_elevator(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/elevators', [
            'building_uuid' => $building->uuid,
            'serial_number' => 'SN-200',
            'name' => 'A Blok Asansoru',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.serial_number', 'SN-200')
            ->assertJsonPath('data.building.uuid', $building->uuid);

        $this->assertDatabaseHas('elevators', [
            'company_id' => $company->id,
            'building_id' => $building->id,
            'serial_number' => 'SN-200',
        ]);
    }

    public function test_creating_an_elevator_ignores_client_supplied_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/elevators', [
            'company_id' => $otherCompany->id,
            'building_uuid' => $building->uuid,
            'serial_number' => 'SN-300',
        ])->assertCreated();

        $this->assertDatabaseHas('elevators', [
            'serial_number' => 'SN-300',
            'company_id' => $company->id,
        ]);
    }

    public function test_creating_an_elevator_requires_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/elevators', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['building_uuid', 'serial_number']],
            ]);
    }

    public function test_creating_an_elevator_with_another_companys_building_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/elevators', [
            'building_uuid' => $otherBuilding->uuid,
            'serial_number' => 'SN-400',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['building_uuid']],
            ]);

        $this->assertDatabaseMissing('elevators', [
            'serial_number' => 'SN-400',
        ]);
    }

    public function test_authenticated_user_can_update_their_own_elevator(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create([
            'building_id' => $building->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/elevators/{$elevator->uuid}", [
            'name' => 'New Name',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('elevators', [
            'id' => $elevator->id,
            'name' => 'New Name',
        ]);
    }

    public function test_updating_another_companys_elevator_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);
        $otherElevator = Elevator::factory()->create(['building_id' => $otherBuilding->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/elevators/{$otherElevator->uuid}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_updating_an_elevator_to_another_companys_building_fails_validation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        $response = $this->actingAs($user)->patchJson("/api/v1/elevators/{$elevator->uuid}", [
            'building_uuid' => $otherBuilding->uuid,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('elevators', [
            'id' => $elevator->id,
            'building_id' => $building->id,
        ]);
    }

    public function test_authenticated_user_can_delete_their_own_elevator(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/elevators/{$elevator->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('elevators', [
            'id' => $elevator->id,
        ]);
    }

    public function test_deleting_another_companys_elevator_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);
        $otherElevator = Elevator::factory()->create(['building_id' => $otherBuilding->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/elevators/{$otherElevator->uuid}")
            ->assertNotFound();
    }
}
