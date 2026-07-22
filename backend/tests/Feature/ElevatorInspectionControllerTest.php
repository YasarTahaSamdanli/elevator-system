<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Elevator;
use App\Models\ElevatorInspection;
use App\Models\InspectionFinding;
use App\Models\ServiceContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElevatorInspectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_inspections(): void
    {
        $this->getJson('/api/v1/elevator-inspections')->assertUnauthorized();
    }

    public function test_authenticated_user_can_list_their_companys_inspections(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $otherElevator = Elevator::factory()->create(['company_id' => $otherCompany->id]);

        ElevatorInspection::factory()->count(2)->create(['elevator_id' => $elevator->id]);
        ElevatorInspection::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/elevator-inspections')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_inspections_can_be_filtered_by_label(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        ElevatorInspection::factory()->label('red')->create(['elevator_id' => $elevator->id]);
        ElevatorInspection::factory()->label('green')->create(['elevator_id' => $elevator->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/elevator-inspections?filter[label]=red')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.label', 'red');
    }

    public function test_unknown_filter_field_returns_validation_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/elevator-inspections?filter[bogus]=1')
            ->assertUnprocessable();
    }

    public function test_authenticated_user_can_view_a_single_inspection_with_findings(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $inspection = ElevatorInspection::factory()->label('yellow')->create([
            'elevator_id' => $elevator->id,
            'report_number' => 'RPT-123',
        ]);
        InspectionFinding::factory()->count(2)->create(['elevator_inspection_id' => $inspection->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/elevator-inspections/{$inspection->uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $inspection->uuid)
            ->assertJsonPath('data.report_number', 'RPT-123')
            ->assertJsonPath('data.label', 'yellow')
            ->assertJsonPath('data.elevator.uuid', $elevator->uuid)
            ->assertJsonCount(2, 'data.findings');
    }

    public function test_viewing_another_companys_inspection_returns_not_found(): void
    {
        $user = User::factory()->create();
        $otherElevator = Elevator::factory()->create();
        $otherInspection = ElevatorInspection::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/elevator-inspections/{$otherInspection->uuid}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_an_inspection_with_findings(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'type' => 'periodic',
            'inspection_body' => 'TSE',
            'inspected_at' => '2026-07-01',
            'label' => 'blue',
            'report_number' => 'RPT-2026-01',
            'next_inspection_date' => '2027-07-01',
            'findings' => [
                ['description' => 'Kabin aydınlatması yetersiz'],
                ['description' => 'Kat kapısı fotoseli arızalı'],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.label', 'blue')
            ->assertJsonPath('data.inspected_at', '2026-07-01')
            ->assertJsonCount(2, 'data.findings');

        $this->assertDatabaseHas('elevator_inspections', [
            'company_id' => $company->id,
            'elevator_id' => $elevator->id,
            'report_number' => 'RPT-2026-01',
            'created_by' => $user->id,
        ]);
        $this->assertSame(2, InspectionFinding::count());
    }

    public function test_creating_an_inspection_updates_the_elevator_label_cache(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-07-01',
            'label' => 'red',
            'next_inspection_date' => '2027-07-01',
        ])->assertCreated();

        $elevator->refresh();
        $this->assertSame('red', $elevator->current_label);
        $this->assertSame('2026-07-01', $elevator->last_inspection_at->toDateString());
        $this->assertSame('2027-07-01', $elevator->next_inspection_due->toDateString());
        // Red label: follow-up suggested 60 days after the inspection
        // (EK 7: GÜVENSİZ → 60 gün).
        $this->assertSame('2026-08-30', $elevator->follow_up_due->toDateString());
    }

    public function test_follow_up_windows_match_the_ek7_form_per_label(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        // GÜVENSİZ (red) → 60 gün
        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-01-01',
            'label' => 'red',
        ])->assertCreated()->assertJsonPath('data.follow_up_due_date', '2026-03-02');

        // KUSURLU (yellow) → 120 gün
        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-01-01',
            'label' => 'yellow',
        ])->assertCreated()->assertJsonPath('data.follow_up_due_date', '2026-05-01');

        // HAFİF KUSURLU (blue) → 12 ay
        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-01-01',
            'label' => 'blue',
        ])->assertCreated()->assertJsonPath('data.follow_up_due_date', '2027-01-01');

        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-01-01',
            'label' => 'green',
        ])->assertCreated()->assertJsonPath('data.follow_up_due_date', null);
    }

    public function test_explicit_follow_up_date_is_not_overridden(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-01-01',
            'label' => 'red',
            'follow_up_due_date' => '2026-01-15',
        ])->assertCreated()->assertJsonPath('data.follow_up_due_date', '2026-01-15');
    }

    public function test_creating_an_inspection_for_another_companys_elevator_fails_validation(): void
    {
        $user = User::factory()->create();
        $otherElevator = Elevator::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $otherElevator->uuid,
            'inspected_at' => '2026-07-01',
            'label' => 'green',
        ])->assertUnprocessable()->assertJsonPath('success', false);
    }

    public function test_the_latest_inspection_wins_the_elevator_label_cache(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2026-06-01',
            'label' => 'red',
        ])->assertCreated();

        // A later follow-up that passes clears the red label and deadline.
        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'type' => 'follow_up',
            'inspected_at' => '2026-06-20',
            'label' => 'green',
        ])->assertCreated();

        // Backfilling an older record must not override the newer one.
        $this->actingAs($user)->postJson('/api/v1/elevator-inspections', [
            'elevator_uuid' => $elevator->uuid,
            'inspected_at' => '2025-06-01',
            'label' => 'yellow',
        ])->assertCreated();

        $elevator->refresh();
        $this->assertSame('green', $elevator->current_label);
        $this->assertSame('2026-06-20', $elevator->last_inspection_at->toDateString());
        $this->assertNull($elevator->follow_up_due);
    }

    public function test_updating_an_inspection_replaces_findings_and_refreshes_cache(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $inspection = ElevatorInspection::factory()->label('green')->create([
            'elevator_id' => $elevator->id,
            'inspected_at' => '2026-07-01',
        ]);
        InspectionFinding::factory()->create(['elevator_inspection_id' => $inspection->id]);

        $this->actingAs($user)->patchJson("/api/v1/elevator-inspections/{$inspection->uuid}", [
            'label' => 'yellow',
            'findings' => [
                ['description' => 'Halat aşınması', 'is_resolved' => false],
                ['description' => 'Paraşüt fren testi başarısız', 'is_resolved' => true],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'yellow')
            ->assertJsonCount(2, 'data.findings')
            ->assertJsonPath('data.findings.1.is_resolved', true);

        $this->assertSame('yellow', $elevator->refresh()->current_label);
    }

    public function test_deleting_an_inspection_recalculates_the_elevator_label_cache(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);

        ElevatorInspection::factory()->label('green')->create([
            'elevator_id' => $elevator->id,
            'inspected_at' => '2026-01-01',
        ]);
        $latest = ElevatorInspection::factory()->label('red')->create([
            'elevator_id' => $elevator->id,
            'inspected_at' => '2026-06-01',
        ]);

        $this->assertSame('red', $elevator->refresh()->current_label);

        $this->actingAs($user)
            ->deleteJson("/api/v1/elevator-inspections/{$latest->uuid}")
            ->assertOk();

        $this->assertSame('green', $elevator->refresh()->current_label);
        $this->assertSoftDeleted('elevator_inspections', ['id' => $latest->id]);
    }

    public function test_a_finding_can_be_marked_resolved(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $inspection = ElevatorInspection::factory()->create(['elevator_id' => $elevator->id]);
        $finding = InspectionFinding::factory()->create(['elevator_inspection_id' => $inspection->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/elevator-inspections/{$inspection->uuid}/findings/{$finding->uuid}", [
                'is_resolved' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_resolved', true);

        $this->assertTrue($finding->refresh()->is_resolved);
    }

    public function test_a_finding_of_a_different_inspection_is_not_found(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        $inspection = ElevatorInspection::factory()->create(['elevator_id' => $elevator->id]);
        $otherInspection = ElevatorInspection::factory()->create(['elevator_id' => $elevator->id]);
        $finding = InspectionFinding::factory()->create(['elevator_inspection_id' => $otherInspection->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/elevator-inspections/{$inspection->uuid}/findings/{$finding->uuid}", [
                'is_resolved' => true,
            ])
            ->assertNotFound();
    }

    public function test_a_repair_work_order_can_be_created_from_an_inspection(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'status' => 'active',
        ]);
        $inspection = ElevatorInspection::factory()->label('red')->create([
            'elevator_id' => $elevator->id,
            'report_number' => 'RPT-777',
        ]);
        InspectionFinding::factory()->count(2)->create(['elevator_inspection_id' => $inspection->id]);
        InspectionFinding::factory()->create([
            'elevator_inspection_id' => $inspection->id,
            'is_resolved' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$inspection->uuid}/work-order");

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.work_order.status', 'draft');

        $inspection->refresh();
        $this->assertNotNull($inspection->work_order_id);

        $workOrder = $inspection->workOrder;
        $this->assertSame('repair', $workOrder->type);
        // Red label defects are critical by definition.
        $this->assertSame('critical', $workOrder->priority);
        $this->assertSame($company->id, $workOrder->company_id);
        // Only the 2 unresolved findings become checklist items.
        $this->assertCount(2, $workOrder->checklistItems);
    }

    public function test_work_order_checklist_is_ordered_like_the_paper_report(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        ServiceContract::factory()->create(['elevator_id' => $elevator->id, 'status' => 'active']);
        $inspection = ElevatorInspection::factory()->label('red')->create(['elevator_id' => $elevator->id]);

        // Created deliberately out of report order.
        InspectionFinding::factory()->create([
            'elevator_inspection_id' => $inspection->id,
            'description' => 'Mavi eksik',
            'severity' => 'blue',
            'item_code' => '1.1.2',
            'position' => 10,
        ]);
        InspectionFinding::factory()->create([
            'elevator_inspection_id' => $inspection->id,
            'description' => 'Sarı eksik',
            'severity' => 'yellow',
            'item_code' => '1.3.1',
            'position' => 4,
        ]);
        InspectionFinding::factory()->create([
            'elevator_inspection_id' => $inspection->id,
            'description' => 'Kırmızı eksik',
            'severity' => 'red',
            'item_code' => '2.7.8',
            'position' => 1,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$inspection->uuid}/work-order")
            ->assertCreated();

        $items = $inspection->refresh()->workOrder->checklistItems()->orderBy('position')->get();

        // Red first, then yellow, then blue — like the paper's sections.
        $this->assertSame(['red', 'yellow', 'blue'], $items->pluck('severity')->all());
        $this->assertSame(['Kırmızı eksik', 'Sarı eksik', 'Mavi eksik'], $items->pluck('label')->all());
        $this->assertSame(['2.7.8', '1.3.1', '1.1.2'], $items->pluck('item_code')->all());
    }

    public function test_creating_a_work_order_twice_for_the_same_inspection_fails(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        ServiceContract::factory()->create(['elevator_id' => $elevator->id, 'status' => 'active']);
        $inspection = ElevatorInspection::factory()->label('yellow')->create(['elevator_id' => $elevator->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$inspection->uuid}/work-order")
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$inspection->uuid}/work-order")
            ->assertUnprocessable();
    }

    public function test_creating_a_work_order_without_an_active_contract_fails(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $elevator = Elevator::factory()->create(['company_id' => $company->id]);
        ServiceContract::factory()->create(['elevator_id' => $elevator->id, 'status' => 'expired']);
        $inspection = ElevatorInspection::factory()->label('red')->create(['elevator_id' => $elevator->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$inspection->uuid}/work-order")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_creating_a_work_order_for_another_companys_inspection_is_not_found(): void
    {
        $user = User::factory()->create();
        $otherElevator = Elevator::factory()->create();
        $otherInspection = ElevatorInspection::factory()->create(['elevator_id' => $otherElevator->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/elevator-inspections/{$otherInspection->uuid}/work-order")
            ->assertNotFound();
    }

    public function test_elevator_list_exposes_and_filters_by_current_label(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $redElevator = Elevator::factory()->create(['company_id' => $company->id]);
        Elevator::factory()->create(['company_id' => $company->id]);

        ElevatorInspection::factory()->label('red')->create(['elevator_id' => $redElevator->id]);

        $this->actingAs($user)
            ->getJson('/api/v1/elevators?filter[current_label]=red')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $redElevator->uuid)
            ->assertJsonPath('data.0.current_label', 'red');
    }
}
