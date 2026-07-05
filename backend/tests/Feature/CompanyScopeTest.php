<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_only_sees_buildings_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $buildingA = Building::factory()->create(['company_id' => $companyA->id]);
        Building::factory()->create(['company_id' => $companyB->id]);

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $this->actingAs($userA);

        $buildings = Building::all();

        $this->assertCount(1, $buildings);
        $this->assertTrue($buildings->first()->is($buildingA));
    }

    public function test_authenticated_user_only_sees_elevators_contracts_and_work_orders_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $buildingA = Building::factory()->create(['company_id' => $companyA->id]);
        $elevatorA = Elevator::factory()->create(['building_id' => $buildingA->id]);
        $contractA = ServiceContract::factory()->create(['elevator_id' => $elevatorA->id]);
        $workOrderA = WorkOrder::factory()->create(['service_contract_id' => $contractA->id]);

        $buildingB = Building::factory()->create(['company_id' => $companyB->id]);
        $elevatorB = Elevator::factory()->create(['building_id' => $buildingB->id]);
        $contractB = ServiceContract::factory()->create(['elevator_id' => $elevatorB->id]);
        WorkOrder::factory()->create(['service_contract_id' => $contractB->id]);

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $this->actingAs($userA);

        $visibleElevators = Elevator::all();
        $visibleContracts = ServiceContract::all();
        $visibleWorkOrders = WorkOrder::all();

        $this->assertNotEmpty($visibleElevators);
        $this->assertNotEmpty($visibleContracts);
        $this->assertNotEmpty($visibleWorkOrders);

        $this->assertCount(1, $visibleElevators);
        $this->assertCount(1, $visibleContracts);
        $this->assertCount(1, $visibleWorkOrders);

        $this->assertTrue($visibleElevators->every(fn (Elevator $e) => $e->is($elevatorA)));
        $this->assertTrue($visibleContracts->every(fn (ServiceContract $c) => $c->is($contractA)));
        $this->assertTrue($visibleWorkOrders->every(fn (WorkOrder $w) => $w->is($workOrderA)));

        $this->assertSame($companyA->id, $elevatorA->company_id);
        $this->assertSame($companyA->id, $contractA->company_id);
        $this->assertSame($companyA->id, $workOrderA->company_id);
    }

    public function test_authenticated_user_only_sees_users_from_their_own_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        User::factory()->create(['company_id' => $companyB->id]);

        $this->actingAs($userA);

        $visibleUsers = User::all();

        $this->assertCount(1, $visibleUsers);
        $this->assertTrue($visibleUsers->first()->is($userA));
    }

    public function test_creating_a_building_without_company_id_defaults_to_authenticated_users_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $building = Building::create([
            'name' => 'Merkez Plaza',
            'address' => 'Test address',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ]);

        $this->assertSame($company->id, $building->company_id);
    }

    public function test_client_supplied_company_id_is_ignored_on_mass_assignment(): void
    {
        $ownCompany = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $ownCompany->id]);

        $this->actingAs($user);

        $building = Building::create([
            'company_id' => $otherCompany->id,
            'name' => 'Merkez Plaza',
            'address' => 'Test address',
            'city' => 'Istanbul',
            'district' => 'Kadikoy',
        ]);

        $this->assertSame($ownCompany->id, $building->company_id);
        $this->assertNotSame($otherCompany->id, $building->company_id);
    }

    public function test_company_scope_is_bypassed_when_no_user_is_authenticated(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Building::factory()->create(['company_id' => $companyA->id]);
        Building::factory()->create(['company_id' => $companyB->id]);

        $this->assertCount(2, Building::all());
    }

    public function test_find_returns_null_for_a_record_belonging_to_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $buildingB = Building::factory()->create(['company_id' => $companyB->id]);

        $this->actingAs($userA);

        $this->assertNull(Building::find($buildingB->id));
    }

    public function test_find_or_fail_throws_not_found_for_a_record_belonging_to_another_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $buildingA = Building::factory()->create(['company_id' => $companyA->id]);
        $elevatorB = Elevator::factory()->create([
            'building_id' => Building::factory()->create(['company_id' => $companyB->id])->id,
        ]);

        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $this->actingAs($userA);

        // Own company's record is still reachable.
        $this->assertTrue(Building::findOrFail($buildingA->id)->is($buildingA));

        // Denormalized company_id on a chained model (Elevator) is enforced too.
        $this->expectException(ModelNotFoundException::class);

        Elevator::findOrFail($elevatorB->id);
    }
}
