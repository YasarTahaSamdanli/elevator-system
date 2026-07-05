<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_buildings(): void
    {
        $this->getJson('/api/v1/buildings')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_buildings(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        Building::factory()->count(2)->create(['company_id' => $company->id]);
        Building::factory()->create(['company_id' => $otherCompany->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/buildings');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_authenticated_user_can_view_a_single_building(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create([
            'company_id' => $company->id,
            'name' => 'Merkez Plaza',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/buildings/{$building->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', $building->uuid)
            ->assertJsonPath('data.name', 'Merkez Plaza');
    }

    public function test_viewing_another_companys_building_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/buildings/{$otherBuilding->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_a_building(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/buildings', [
            'name' => 'Merkez Plaza',
            'code' => 'MRK-001',
            'address' => 'Test address',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Merkez Plaza')
            ->assertJsonPath('data.code', 'MRK-001');

        $this->assertDatabaseHas('buildings', [
            'company_id' => $company->id,
            'name' => 'Merkez Plaza',
            'code' => 'MRK-001',
        ]);
    }

    public function test_creating_a_building_ignores_client_supplied_company_id(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/buildings', [
            'company_id' => $otherCompany->id,
            'name' => 'Merkez Plaza',
            'address' => 'Test address',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ])->assertCreated();

        $this->assertDatabaseHas('buildings', [
            'name' => 'Merkez Plaza',
            'company_id' => $company->id,
        ]);
        $this->assertDatabaseMissing('buildings', [
            'name' => 'Merkez Plaza',
            'company_id' => $otherCompany->id,
        ]);
    }

    public function test_creating_a_building_requires_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/buildings', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['details' => ['name', 'address', 'city', 'district']],
            ]);
    }

    public function test_building_code_must_be_unique_within_company_on_create(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Building::factory()->create([
            'company_id' => $company->id,
            'code' => 'DUP-001',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/buildings', [
            'name' => 'Another Plaza',
            'code' => 'DUP-001',
            'address' => 'Test address',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_authenticated_user_can_update_their_own_building(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create([
            'company_id' => $company->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/v1/buildings/{$building->uuid}", [
            'name' => 'New Name',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('buildings', [
            'id' => $building->id,
            'name' => 'New Name',
        ]);
    }

    public function test_updating_another_companys_building_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$otherBuilding->uuid}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_authenticated_user_can_delete_their_own_building(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/buildings/{$building->uuid}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('buildings', [
            'id' => $building->id,
        ]);
    }

    public function test_deleting_another_companys_building_returns_not_found(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $otherCompany->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$otherBuilding->uuid}")
            ->assertNotFound();
    }
}
