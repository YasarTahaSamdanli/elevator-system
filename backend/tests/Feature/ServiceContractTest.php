<?php

namespace Tests\Feature;

use App\Models\Elevator;
use App\Models\ServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_contract_can_be_created(): void
    {
        $elevator = Elevator::factory()->create();

        $serviceContract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-001',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('service_contracts', [
            'id' => $serviceContract->id,
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-001',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'active',
        ]);
    }

    public function test_service_contract_uuid_is_generated_automatically(): void
    {
        $serviceContract = ServiceContract::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($serviceContract->uuid);
        $this->assertTrue(Str::isUuid($serviceContract->uuid));
    }

    public function test_service_contract_elevator_relationship_works(): void
    {
        $elevator = Elevator::factory()->create([
            'serial_number' => 'SN-001',
        ]);

        $serviceContract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
        ]);

        $this->assertTrue($serviceContract->elevator->is($elevator));
        $this->assertSame('SN-001', $serviceContract->elevator->serial_number);
        $this->assertTrue($elevator->serviceContracts->contains($serviceContract));
    }

    public function test_multiple_service_contracts_can_be_created_for_same_elevator(): void
    {
        $elevator = Elevator::factory()->create();

        ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-001',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'expired',
        ]);

        $currentContract = ServiceContract::factory()->create([
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-002',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $this->assertSame(2, $elevator->serviceContracts()->count());
        $this->assertDatabaseHas('service_contracts', [
            'id' => $currentContract->id,
            'elevator_id' => $elevator->id,
            'contract_number' => 'CNT-002',
        ]);
    }
}
