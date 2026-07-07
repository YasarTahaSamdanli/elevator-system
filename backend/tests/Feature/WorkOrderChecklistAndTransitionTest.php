<?php

namespace Tests\Feature;

use App\Models\ChecklistTemplate;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderChecklistAndTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private ServiceContract $serviceContract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $this->company->id]);
        $this->serviceContract = ServiceContract::factory()->create(['elevator_id' => $elevator->id]);
    }

    private function createTemplate(Company $company, string $type = 'maintenance', array $labels = ['Fren testi', 'Halat kontrolü']): ChecklistTemplate
    {
        $template = new ChecklistTemplate([
            'name' => 'Test Şablonu',
            'work_order_type' => $type,
            'is_active' => true,
        ]);
        $template->company_id = $company->id;
        $template->save();

        foreach ($labels as $index => $label) {
            $template->items()->create(['position' => $index + 1, 'label' => $label]);
        }

        return $template;
    }

    public function test_creating_a_work_order_copies_the_matching_checklist_template(): void
    {
        $this->createTemplate($this->company);

        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'maintenance',
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.checklist')
            ->assertJsonPath('data.checklist.0.label', 'Fren testi')
            ->assertJsonPath('data.checklist.0.is_done', false);

        $this->assertDatabaseCount('work_order_checklist_items', 2);
    }

    public function test_checklist_is_not_copied_for_a_different_work_order_type(): void
    {
        $this->createTemplate($this->company, 'maintenance');

        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'fault',
        ]);

        $response->assertCreated()->assertJsonCount(0, 'data.checklist');
    }

    public function test_another_companys_template_is_not_copied(): void
    {
        $otherCompany = Company::factory()->create();
        $this->createTemplate($otherCompany);

        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'maintenance',
        ]);

        $response->assertCreated()->assertJsonCount(0, 'data.checklist');
    }

    public function test_checklist_item_can_be_toggled_and_annotated(): void
    {
        $this->createTemplate($this->company);

        $created = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'maintenance',
        ])->json('data');

        $itemUuid = $created['checklist'][0]['uuid'];

        $response = $this->actingAs($this->user)->patchJson(
            "/api/v1/work-orders/{$created['uuid']}/checklist-items/{$itemUuid}",
            ['is_done' => true, 'note' => 'Balata değişti'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.is_done', true)
            ->assertJsonPath('data.note', 'Balata değişti');
    }

    public function test_checklist_item_of_another_work_order_returns_not_found(): void
    {
        $this->createTemplate($this->company);

        $first = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'maintenance',
        ])->json('data');

        $second = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'service_contract_uuid' => $this->serviceContract->uuid,
            'type' => 'maintenance',
        ])->json('data');

        // Item belongs to $first, addressed through $second → scoped binding must 404.
        $this->actingAs($this->user)
            ->patchJson(
                "/api/v1/work-orders/{$second['uuid']}/checklist-items/{$first['checklist'][0]['uuid']}",
                ['is_done' => true],
            )
            ->assertNotFound();
    }

    public function test_forward_status_transitions_are_allowed(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'draft',
        ]);

        foreach (['planned', 'assigned', 'in_progress', 'completed'] as $status) {
            $this->actingAs($this->user)
                ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }
    }

    public function test_skipping_forward_statuses_is_allowed(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_backward_status_transition_is_rejected(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'planned'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['status']]]);
    }

    public function test_completed_and_cancelled_work_orders_cannot_change_status(): void
    {
        foreach (['completed', 'cancelled'] as $terminal) {
            $workOrder = WorkOrder::factory()->create([
                'service_contract_id' => $this->serviceContract->id,
                'status' => $terminal,
            ]);

            $this->actingAs($this->user)
                ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
                ->assertUnprocessable();
        }
    }

    public function test_cancellation_is_allowed_from_any_active_status(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_starting_work_autofills_started_at(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'assigned',
            'started_at' => null,
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'in_progress'])
            ->assertOk();

        $this->assertNotNull($workOrder->fresh()->started_at);
    }

    public function test_completing_work_autofills_completed_at(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'in_progress',
            'completed_at' => null,
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", ['status' => 'completed'])
            ->assertOk();

        $this->assertNotNull($workOrder->fresh()->completed_at);
    }

    public function test_explicit_timestamps_are_not_overwritten_by_autofill(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $this->serviceContract->id,
            'status' => 'assigned',
            'started_at' => null,
        ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/work-orders/{$workOrder->uuid}", [
                'status' => 'in_progress',
                'started_at' => '2026-07-01T08:30:00Z',
            ])
            ->assertOk();

        $this->assertSame(
            '2026-07-01 08:30:00',
            $workOrder->fresh()->started_at->utc()->format('Y-m-d H:i:s'),
        );
    }
}
