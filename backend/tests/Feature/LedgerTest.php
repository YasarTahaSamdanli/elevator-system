<?php

namespace Tests\Feature;

use App\Models\AccountTransaction;
use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\Material;
use App\Models\PaymentMethod;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerTest extends TestCase
{
    use RefreshDatabase;

    /* ---------- payment methods ---------- */

    public function test_unauthenticated_user_cannot_list_payment_methods(): void
    {
        $this->getJson('/api/v1/payment-methods')->assertUnauthorized();
    }

    public function test_payment_methods_are_company_scoped(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        PaymentMethod::factory()->create(['company_id' => $company->id, 'name' => 'Banka GR']);
        PaymentMethod::factory()->create(); // other company

        $this->actingAs($user)
            ->getJson('/api/v1/payment-methods')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Banka GR');
    }

    public function test_a_payment_method_can_be_created_and_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/payment-methods', ['name' => 'Elden'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Elden');

        $uuid = $response->json('data.uuid');

        $this->actingAs($user)
            ->patchJson("/api/v1/payment-methods/{$uuid}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_used_payment_method_cannot_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);
        AccountTransaction::factory()->ofType('payment')->create([
            'building_id' => $building->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/payment-methods/{$paymentMethod->uuid}")
            ->assertUnprocessable();

        $this->assertNotSoftDeleted('payment_methods', ['id' => $paymentMethod->id]);
    }

    /* ---------- account transactions ---------- */

    public function test_account_transactions_are_company_scoped_and_filterable(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $company->id]);

        AccountTransaction::factory()->ofType('maintenance_fee')->create(['building_id' => $building->id]);
        AccountTransaction::factory()->ofType('payment')->create(['building_id' => $building->id]);
        AccountTransaction::factory()->ofType('maintenance_fee')->create(['building_id' => $otherBuilding->id]);
        AccountTransaction::factory()->create(); // other company

        $this->actingAs($user)
            ->getJson('/api/v1/account-transactions')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $this->actingAs($user)
            ->getJson("/api/v1/account-transactions?filter[building_uuid]={$building->uuid}&filter[type]=payment")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'payment');
    }

    public function test_a_payment_can_be_recorded_and_sets_the_collector(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/account-transactions', [
            'building_uuid' => $building->uuid,
            'type' => 'payment',
            'amount' => 1500.50,
            'occurred_at' => '2026-07-09',
            'payment_method_uuid' => $paymentMethod->uuid,
            'payer_name' => 'Metin Yalçın',
            'description' => 'Haziran bakım tahsilatı',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'payment')
            ->assertJsonPath('data.signed_amount', '-1500.50')
            ->assertJsonPath('data.collected_by.uuid', $user->uuid)
            ->assertJsonPath('data.payment_method.name', $paymentMethod->name);

        $this->assertDatabaseHas('account_transactions', [
            'company_id' => $company->id,
            'building_id' => $building->id,
            'type' => 'payment',
            'collected_by' => $user->id,
            'created_by' => $user->id,
        ]);
    }

    public function test_an_elevator_from_another_building_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $company->id]);
        $foreignElevator = Elevator::factory()->create(['building_id' => $otherBuilding->id]);

        $this->actingAs($user)->postJson('/api/v1/account-transactions', [
            'building_uuid' => $building->uuid,
            'elevator_uuid' => $foreignElevator->uuid,
            'type' => 'opening_balance',
            'amount' => 900,
            'occurred_at' => '2026-01-01',
        ])->assertUnprocessable();
    }

    public function test_another_companys_building_is_rejected(): void
    {
        $user = User::factory()->create();
        $foreignBuilding = Building::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/account-transactions', [
            'building_uuid' => $foreignBuilding->uuid,
            'type' => 'payment',
            'amount' => 100,
            'occurred_at' => '2026-07-09',
        ])->assertUnprocessable();
    }

    public function test_the_ledger_has_no_update_or_delete_endpoints(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $transaction = AccountTransaction::factory()->create(['building_id' => $building->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/account-transactions/{$transaction->uuid}", ['amount' => 1])
            ->assertStatus(405);

        $this->actingAs($user)
            ->deleteJson("/api/v1/account-transactions/{$transaction->uuid}")
            ->assertStatus(405);
    }

    public function test_summary_returns_totals_and_balance_for_a_building(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $otherBuilding = Building::factory()->create(['company_id' => $company->id]);

        AccountTransaction::factory()->ofType('opening_balance')->create(['building_id' => $building->id, 'amount' => 900]);
        AccountTransaction::factory()->ofType('maintenance_fee')->create(['building_id' => $building->id, 'amount' => 1400]);
        AccountTransaction::factory()->ofType('part_charge')->create(['building_id' => $building->id, 'amount' => 720]);
        AccountTransaction::factory()->ofType('payment')->create(['building_id' => $building->id, 'amount' => 2000]);
        // noise on another building
        AccountTransaction::factory()->ofType('maintenance_fee')->create(['building_id' => $otherBuilding->id, 'amount' => 999]);

        $this->actingAs($user)
            ->getJson("/api/v1/account-transactions/summary?building_uuid={$building->uuid}")
            ->assertOk()
            ->assertJsonPath('data.totals.opening_balance', 900)
            ->assertJsonPath('data.totals.maintenance_fee', 1400)
            ->assertJsonPath('data.totals.part_charge', 720)
            ->assertJsonPath('data.totals.payment', 2000)
            ->assertJsonPath('data.charges_total', 3020)
            ->assertJsonPath('data.credits_total', 2000)
            ->assertJsonPath('data.balance', 1020);
    }

    /* ---------- monthly accrual ---------- */

    public function test_accrual_command_posts_monthly_fees_once_per_contract(): void
    {
        $company = Company::factory()->create();
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);
        $contract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
            'monthly_fee' => 1400,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        // Should be skipped: expired contract and contract without a fee.
        ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'expired',
            'monthly_fee' => 1000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);
        ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
            'monthly_fee' => null,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->artisan('ledger:accrue-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 1 maintenance fee accrual(s)')
            ->assertSuccessful();

        $this->assertDatabaseHas('account_transactions', [
            'company_id' => $company->id,
            'building_id' => $building->id,
            'elevator_id' => $elevator->id,
            'service_contract_id' => $contract->id,
            'type' => 'maintenance_fee',
        ]);
        $this->assertSame(1, AccountTransaction::withoutGlobalScopes()->count());
        $this->assertSame(
            '2026-07-01',
            AccountTransaction::withoutGlobalScopes()->first()->occurred_at->toDateString(),
        );

        // Second run must be a no-op.
        $this->artisan('ledger:accrue-maintenance', ['--month' => '2026-07'])
            ->expectsOutputToContain('Created 0 maintenance fee accrual(s)')
            ->assertSuccessful();

        $this->assertSame(1, AccountTransaction::withoutGlobalScopes()->count());
    }

    public function test_accrual_skips_contracts_outside_their_term(): void
    {
        $company = Company::factory()->create();
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);
        ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
            'monthly_fee' => 1400,
            'start_date' => '2026-09-01',
            'end_date' => '2027-08-31',
        ]);

        $this->artisan('ledger:accrue-maintenance', ['--month' => '2026-07'])->assertSuccessful();

        $this->assertSame(0, AccountTransaction::withoutGlobalScopes()->count());
    }

    /* ---------- completion charges ---------- */

    /**
     * @return array{0: User, 1: WorkOrder, 2: Elevator}
     */
    private function createInProgressWorkOrder(string $type = 'maintenance'): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $building = Building::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);
        $contract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
        ]);
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $contract->id,
            'type' => $type,
            'status' => 'in_progress',
        ]);

        return [$user, $workOrder, $elevator];
    }

    public function test_completing_a_work_order_charges_the_sale_price_to_the_ledger(): void
    {
        [$user, $workOrder, $elevator] = $this->createInProgressWorkOrder();
        $material = Material::factory()->create(['company_id' => $workOrder->company_id]);
        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 2,
            'unit_price' => 200, // cost
            'sale_unit_price' => 360, // customer price
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('account_transactions', [
            'work_order_id' => $workOrder->id,
            'type' => 'part_charge',
            'building_id' => $elevator->building_id,
            'elevator_id' => $elevator->id,
        ]);
        $this->assertSame(
            '720.00',
            AccountTransaction::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->first()->amount,
        );
    }

    public function test_repair_work_orders_post_as_revision_charge(): void
    {
        [$user, $workOrder] = $this->createInProgressWorkOrder(type: 'repair');
        $material = Material::factory()->create(['company_id' => $workOrder->company_id]);
        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 1,
            'sale_unit_price' => 5000,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertDatabaseHas('account_transactions', [
            'work_order_id' => $workOrder->id,
            'type' => 'revision_charge',
        ]);
    }

    public function test_charge_falls_back_to_cost_when_no_sale_price_is_set(): void
    {
        [$user, $workOrder] = $this->createInProgressWorkOrder();
        $material = Material::factory()->create(['company_id' => $workOrder->company_id, 'default_sale_price' => null]);
        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 3,
            'unit_price' => 100,
            'sale_unit_price' => null,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertSame(
            '300.00',
            AccountTransaction::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->first()->amount,
        );
    }

    public function test_completing_twice_does_not_duplicate_the_charge(): void
    {
        [$user, $workOrder] = $this->createInProgressWorkOrder();
        $material = Material::factory()->create(['company_id' => $workOrder->company_id]);
        $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => 1,
            'sale_unit_price' => 250,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();
        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertSame(
            1,
            AccountTransaction::withoutGlobalScopes()->where('work_order_id', $workOrder->id)->count(),
        );
    }

    public function test_a_work_order_without_materials_creates_no_charge(): void
    {
        [$user, $workOrder] = $this->createInProgressWorkOrder();

        $this->actingAs($user)
            ->patchJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertSame(0, AccountTransaction::withoutGlobalScopes()->count());
    }
}
