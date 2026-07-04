<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuildingTest extends TestCase
{
    use RefreshDatabase;

    public function test_building_can_be_created(): void
    {
        $company = Company::factory()->create();

        $building = Building::factory()->create([
            'company_id' => $company->id,
            'name' => 'Merkez Plaza',
            'code' => 'MERKEZ-001',
        ]);

        $this->assertDatabaseHas('buildings', [
            'id' => $building->id,
            'company_id' => $company->id,
            'name' => 'Merkez Plaza',
            'code' => 'MERKEZ-001',
            'is_active' => true,
        ]);
    }

    public function test_building_uuid_is_generated_automatically(): void
    {
        $building = Building::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($building->uuid);
        $this->assertTrue(Str::isUuid($building->uuid));
    }

    public function test_building_company_relationship_works(): void
    {
        $company = Company::factory()->create([
            'name' => 'Tenant Company',
        ]);

        $building = Building::factory()->create([
            'company_id' => $company->id,
        ]);

        $this->assertTrue($building->company->is($company));
        $this->assertSame('Tenant Company', $building->company->name);
        $this->assertTrue($company->buildings->contains($building));
    }

    public function test_building_code_must_be_unique_within_same_company(): void
    {
        $company = Company::factory()->create();

        Building::factory()->create([
            'company_id' => $company->id,
            'code' => 'BLD-001',
        ]);

        $this->expectException(QueryException::class);

        Building::factory()->create([
            'company_id' => $company->id,
            'code' => 'BLD-001',
        ]);
    }

    public function test_building_code_can_be_used_by_different_companies(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();

        Building::factory()->create([
            'company_id' => $firstCompany->id,
            'code' => 'BLD-001',
        ]);

        $building = Building::factory()->create([
            'company_id' => $secondCompany->id,
            'code' => 'BLD-001',
        ]);

        $this->assertDatabaseHas('buildings', [
            'id' => $building->id,
            'company_id' => $secondCompany->id,
            'code' => 'BLD-001',
        ]);
    }
}
