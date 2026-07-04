<?php

namespace Tests\Unit;

use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_uses_soft_deletes(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $workOrder->delete();

        $this->assertSoftDeleted('work_orders', [
            'id' => $workOrder->id,
        ]);
    }
}
