<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * List endpoint conventions (SOLUTION_ARCHITECTURE.md §12): pagination via
 * page/per_page with meta.pagination, filter[field]=value, date ranges via
 * filter[col_from]/filter[col_to], sort=-field,other and search.
 */
class ApiListQueryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_index_returns_pagination_meta_with_defaults(): void
    {
        Building::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'success' => true,
                'meta' => [
                    'pagination' => [
                        'page' => 1,
                        'per_page' => ListQuery::DEFAULT_PER_PAGE,
                        'total' => 3,
                        'total_pages' => 1,
                    ],
                ],
            ]);
    }

    public function test_page_and_per_page_parameters_are_respected(): void
    {
        Building::factory()->count(5)->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?per_page=2&page=3');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'meta' => [
                    'pagination' => [
                        'page' => 3,
                        'per_page' => 2,
                        'total' => 5,
                        'total_pages' => 3,
                    ],
                ],
            ]);
    }

    public function test_per_page_above_maximum_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/buildings?per_page='.(ListQuery::MAX_PER_PAGE + 1));

        $response
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR']])
            ->assertJsonStructure(['error' => ['details' => ['per_page']]]);
    }

    public function test_non_integer_per_page_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?per_page=abc');

        $response->assertStatus(422);
    }

    public function test_filter_by_column_equality(): void
    {
        Building::factory()->create(['company_id' => $this->company->id, 'city' => 'Istanbul']);
        Building::factory()->create(['company_id' => $this->company->id, 'city' => 'Ankara']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?filter[city]=Ankara');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.city', 'Ankara');
    }

    public function test_filter_accepts_boolean_strings(): void
    {
        Building::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
        Building::factory()->create(['company_id' => $this->company->id, 'is_active' => false]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?filter[is_active]=false');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_filter_with_array_value_becomes_where_in(): void
    {
        $this->seedWorkOrders(['draft', 'planned', 'completed']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/work-orders?filter[status][]=draft&filter[status][]=planned');

        $response->assertOk();

        $statuses = array_column($response->json('data'), 'status');
        $this->assertNotEmpty($statuses);
        $this->assertEmpty(array_diff($statuses, ['draft', 'planned']));
    }

    public function test_unknown_filter_field_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?filter[company_id]=1');

        $response
            ->assertStatus(422)
            ->assertJson(['error' => ['code' => 'VALIDATION_ERROR']]);
    }

    public function test_filter_by_relation_uuid(): void
    {
        $buildingA = Building::factory()->create(['company_id' => $this->company->id]);
        $buildingB = Building::factory()->create(['company_id' => $this->company->id]);
        Elevator::factory()->count(2)->create(['building_id' => $buildingA->id]);
        Elevator::factory()->create(['building_id' => $buildingB->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/elevators?filter[building_uuid]='.$buildingA->uuid);

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_date_range_filters(): void
    {
        $this->seedWorkOrders(['planned', 'planned', 'planned']);
        WorkOrder::query()->withoutGlobalScopes()->get()->each(function (WorkOrder $wo, int $i): void {
            $wo->forceFill(['scheduled_at' => '2026-0'.($i + 1).'-15 09:00:00'])->save();
        });

        $response = $this->actingAs($this->user)->getJson(
            '/api/v1/work-orders?filter[scheduled_at_from]=2026-02-01&filter[scheduled_at_to]=2026-02-28'
        );

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_invalid_date_range_value_is_rejected(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/work-orders?filter[scheduled_at_from]=not-a-date');

        $response->assertStatus(422);
    }

    public function test_sort_ascending_and_descending(): void
    {
        Building::factory()->create(['company_id' => $this->company->id, 'name' => 'Beta']);
        Building::factory()->create(['company_id' => $this->company->id, 'name' => 'Alfa']);

        $asc = $this->actingAs($this->user)->getJson('/api/v1/buildings?sort=name');
        $asc->assertOk()->assertJsonPath('data.0.name', 'Alfa');

        $desc = $this->actingAs($this->user)->getJson('/api/v1/buildings?sort=-name');
        $desc->assertOk()->assertJsonPath('data.0.name', 'Beta');
    }

    public function test_unknown_sort_field_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?sort=password');

        $response
            ->assertStatus(422)
            ->assertJson(['error' => ['code' => 'VALIDATION_ERROR']]);
    }

    public function test_search_matches_whitelisted_columns_case_insensitively(): void
    {
        Building::factory()->create(['company_id' => $this->company->id, 'name' => 'Nurol Tower']);
        Building::factory()->create(['company_id' => $this->company->id, 'name' => 'Palladium']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/buildings?search=nurol');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nurol Tower');
    }

    public function test_filters_never_leak_other_companies_data(): void
    {
        $otherCompany = Company::factory()->create();
        Building::factory()->create([
            'company_id' => $otherCompany->id,
            'city' => 'Izmir',
            'name' => 'Yabanci Bina',
        ]);
        Building::factory()->create([
            'company_id' => $this->company->id,
            'city' => 'Izmir',
            'name' => 'Kendi Binamiz',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/buildings?filter[city]=Izmir&search=bina');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kendi Binamiz');
    }

    /**
     * @param  list<string>  $statuses
     */
    private function seedWorkOrders(array $statuses): void
    {
        $building = Building::factory()->create(['company_id' => $this->company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        foreach ($statuses as $status) {
            WorkOrder::factory()->create([
                'status' => $status,
                'service_contract_id' => \App\Models\ServiceContract::factory()
                    ->create(['elevator_id' => $elevator->id])->id,
            ]);
        }
    }
}
