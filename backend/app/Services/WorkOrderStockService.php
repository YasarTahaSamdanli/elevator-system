<?php

namespace App\Services;

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;

class WorkOrderStockService
{
    public function issueMaterialsForCompletion(WorkOrder $workOrder): void
    {
        if ($workOrder->items()->doesntExist()) {
            return;
        }

        $alreadyIssued = StockMovement::query()
            ->where('work_order_id', $workOrder->id)
            ->where('type', 'work_order_out')
            ->exists();

        if ($alreadyIssued) {
            return;
        }

        $warehouse = $this->resolveIssueWarehouse($workOrder);

        foreach ($workOrder->items()->with('material')->get() as $item) {
            $movement = new StockMovement;
            $movement->forceFill([
                'company_id' => $workOrder->company_id,
                'material_id' => $item->material_id,
                'warehouse_id' => $warehouse->id,
                'type' => 'work_order_out',
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'work_order_id' => $workOrder->id,
                'occurred_at' => $workOrder->completed_at ?? now(),
                'note' => "İş emri tamamlandı: {$workOrder->work_order_number}",
            ])->save();
        }
    }

    private function resolveIssueWarehouse(WorkOrder $workOrder): Warehouse
    {
        // Filter on the work order's company explicitly: the CompanyScope
        // global scope is skipped in unauthenticated contexts (seeder,
        // queued jobs), where an unqualified query would leak across
        // companies.
        if ($workOrder->assigned_user_id !== null) {
            $vehicleWarehouse = Warehouse::query()
                ->where('company_id', $workOrder->company_id)
                ->where('type', 'vehicle')
                ->where('user_id', $workOrder->assigned_user_id)
                ->where('is_active', true)
                ->first();

            if ($vehicleWarehouse) {
                return $vehicleWarehouse;
            }
        }

        $mainWarehouse = Warehouse::query()
            ->where('company_id', $workOrder->company_id)
            ->where('type', 'main')
            ->where('is_active', true)
            ->first();

        if ($mainWarehouse) {
            return $mainWarehouse;
        }

        $warehouse = new Warehouse([
            'name' => 'Merkez Depo',
            'type' => 'main',
            'is_active' => true,
        ]);
        $warehouse->company_id = $workOrder->company_id;
        $warehouse->save();

        return $warehouse;
    }
}
