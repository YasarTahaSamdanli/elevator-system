<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceWorkOrderGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function createContract(array $attributes = [], ?Company $company = null): ServiceContract
    {
        $company ??= Company::factory()->create();
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        return ServiceContract::factory()->create(array_merge([
            'elevator_id' => $elevator->id,
            'status' => 'active',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $attributes));
    }

    public function test_generation_creates_a_planned_maintenance_work_order_per_active_contract(): void
    {
        $contract = $this->createContract();

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 1 maintenance work order(s)')
            ->assertSuccessful();

        $workOrder = WorkOrder::withoutGlobalScopes()->sole();
        $this->assertSame($contract->id, $workOrder->service_contract_id);
        $this->assertSame($contract->company_id, $workOrder->company_id);
        $this->assertSame('maintenance', $workOrder->type);
        $this->assertSame('planned', $workOrder->status);
        $this->assertSame('normal', $workOrder->priority);
        $this->assertSame('2026-07-01', $workOrder->scheduled_at->toDateString());
        $this->assertSame('Temmuz 2026 periyodik bakım', $workOrder->description);
        $this->assertNotEmpty($workOrder->work_order_number);
    }

    public function test_generation_is_idempotent_per_contract_and_month(): void
    {
        $this->createContract();

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])->assertSuccessful();
        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 0 maintenance work order(s)')
            ->assertSuccessful();

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->count());
    }

    public function test_generation_creates_separate_orders_for_different_months(): void
    {
        $this->createContract();

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])->assertSuccessful();
        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-08'])
            ->expectsOutputToContain('Created 1 maintenance work order(s)')
            ->assertSuccessful();

        $this->assertSame(2, WorkOrder::withoutGlobalScopes()->count());
    }

    public function test_an_existing_manual_maintenance_order_in_the_month_blocks_generation(): void
    {
        $contract = $this->createContract();
        WorkOrder::factory()->create([
            'service_contract_id' => $contract->id,
            'company_id' => $contract->company_id,
            'type' => 'maintenance',
            'scheduled_at' => '2026-07-15 09:00:00',
        ]);

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 0 maintenance work order(s)')
            ->assertSuccessful();

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->count());
    }

    public function test_a_soft_deleted_order_still_counts_for_idempotency(): void
    {
        $this->createContract();

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])->assertSuccessful();
        WorkOrder::withoutGlobalScopes()->sole()->delete();

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 0 maintenance work order(s)')
            ->assertSuccessful();

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->withTrashed()->count());
    }

    public function test_generation_skips_inactive_and_out_of_term_contracts(): void
    {
        $this->createContract(['status' => 'expired']);
        $this->createContract(['start_date' => '2026-09-01', 'end_date' => '2027-08-31']);
        $this->createContract(['start_date' => '2025-01-01', 'end_date' => '2026-06-30']);

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 0 maintenance work order(s)')
            ->assertSuccessful();

        $this->assertSame(0, WorkOrder::withoutGlobalScopes()->count());
    }

    public function test_generation_covers_every_company(): void
    {
        $contractA = $this->createContract();
        $contractB = $this->createContract();

        $this->assertNotSame($contractA->company_id, $contractB->company_id);

        $this->artisan('work-orders:generate-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 2 maintenance work order(s)')
            ->assertSuccessful();

        foreach ([$contractA, $contractB] as $contract) {
            $this->assertDatabaseHas('work_orders', [
                'service_contract_id' => $contract->id,
                'company_id' => $contract->company_id,
                'type' => 'maintenance',
            ]);
        }
    }

    public function test_an_invalid_month_option_fails(): void
    {
        $this->artisan('work-orders:generate-maintenance', ['--month' => 'temmuz'])
            ->expectsOutputToContain('Invalid --month value')
            ->assertFailed();
    }
}
