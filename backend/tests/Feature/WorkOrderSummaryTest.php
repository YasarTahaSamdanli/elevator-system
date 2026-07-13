<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function createContract(?Company $company = null): ServiceContract
    {
        $company ??= Company::factory()->create();
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        return ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
        ]);
    }

    public function test_unauthenticated_user_cannot_view_the_summary(): void
    {
        $this->getJson('/api/v1/work-orders/summary')->assertUnauthorized();
    }

    public function test_summary_counts_are_computed_per_definition(): void
    {
        $contract = $this->createContract();
        $user = User::factory()->create(['company_id' => $contract->company_id]);
        $date = '2026-07-11';

        $make = fn (array $attributes) => WorkOrder::factory()->create(array_merge([
            'service_contract_id' => $contract->id,
        ], $attributes));

        // scheduled_today: planned + assigned today count, cancelled today does not.
        $make(['status' => 'planned', 'scheduled_at' => "{$date} 09:00:00"]);
        $make(['status' => 'assigned', 'scheduled_at' => "{$date} 11:00:00"]);
        $make(['status' => 'cancelled', 'scheduled_at' => "{$date} 13:00:00"]);

        // assigned counts regardless of the scheduled date.
        $make(['status' => 'assigned', 'scheduled_at' => '2026-07-20 09:00:00']);

        // in_progress counts regardless of dates.
        $make(['status' => 'in_progress', 'scheduled_at' => '2026-07-10 09:00:00']);

        // completed_today follows completed_at, not scheduled_at.
        $make([
            'status' => 'completed',
            'scheduled_at' => '2026-07-10 09:00:00',
            'completed_at' => "{$date} 15:00:00",
        ]);
        $make([
            'status' => 'completed',
            'scheduled_at' => "{$date} 08:00:00",
            'completed_at' => '2026-07-10 17:00:00',
        ]);

        $this->actingAs($user)
            ->getJson("/api/v1/work-orders/summary?date={$date}")
            ->assertOk()
            ->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.scheduled_today', 3) // planned + assigned + completed scheduled today
            ->assertJsonPath('data.assigned', 2)
            ->assertJsonPath('data.in_progress', 1)
            ->assertJsonPath('data.completed_today', 1);
    }

    public function test_summary_defaults_to_the_current_date(): void
    {
        $contract = $this->createContract();
        $user = User::factory()->create(['company_id' => $contract->company_id]);

        WorkOrder::factory()->create([
            'service_contract_id' => $contract->id,
            'status' => 'planned',
            'scheduled_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/work-orders/summary')
            ->assertOk()
            ->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonPath('data.scheduled_today', 1);
    }

    public function test_summary_never_counts_other_companies_work_orders(): void
    {
        $contract = $this->createContract();
        $user = User::factory()->create(['company_id' => $contract->company_id]);

        $otherContract = $this->createContract(); // different company
        WorkOrder::factory()->create([
            'service_contract_id' => $otherContract->id,
            'status' => 'in_progress',
            'scheduled_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/work-orders/summary')
            ->assertOk()
            ->assertJsonPath('data.scheduled_today', 0)
            ->assertJsonPath('data.assigned', 0)
            ->assertJsonPath('data.in_progress', 0)
            ->assertJsonPath('data.completed_today', 0);
    }

    public function test_an_invalid_date_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/work-orders/summary?date=11.07.2026')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
