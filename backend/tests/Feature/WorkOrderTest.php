<?php

namespace Tests\Feature;

use App\Models\ServiceContract;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_can_be_created(): void
    {
        $serviceContract = ServiceContract::factory()->create();

        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
            'type' => 'maintenance',
            'priority' => 'normal',
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'service_contract_id' => $serviceContract->id,
            'type' => 'maintenance',
            'status' => 'draft',
            'priority' => 'normal',
        ]);
    }

    public function test_work_order_uuid_is_generated_automatically(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'uuid' => null,
        ]);

        $this->assertNotEmpty($workOrder->uuid);
        $this->assertTrue(Str::isUuid($workOrder->uuid));
    }

    public function test_work_order_number_is_generated_automatically_and_uniquely(): void
    {
        $firstWorkOrder = WorkOrder::factory()->create();
        $secondWorkOrder = WorkOrder::factory()->create();

        $this->assertNotEmpty($firstWorkOrder->work_order_number);
        $this->assertNotEmpty($secondWorkOrder->work_order_number);
        $this->assertNotSame($firstWorkOrder->work_order_number, $secondWorkOrder->work_order_number);
        $this->assertStringStartsWith('WO-', $firstWorkOrder->work_order_number);
    }

    public function test_work_order_service_contract_relationship_works(): void
    {
        $serviceContract = ServiceContract::factory()->create([
            'contract_number' => 'CNT-001',
        ]);

        $workOrder = WorkOrder::factory()->create([
            'service_contract_id' => $serviceContract->id,
        ]);

        $this->assertTrue($workOrder->serviceContract->is($serviceContract));
        $this->assertSame('CNT-001', $workOrder->serviceContract->contract_number);
        $this->assertTrue($serviceContract->workOrders->contains($workOrder));
    }
}
