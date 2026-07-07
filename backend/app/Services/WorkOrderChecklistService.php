<?php

namespace App\Services;

use App\Models\ChecklistTemplate;
use App\Models\WorkOrder;

class WorkOrderChecklistService
{
    /**
     * Copy the company's active checklist template for the work order's
     * type onto the work order. Items are snapshots: later template edits
     * must not change already-issued work orders.
     */
    public function applyTemplate(WorkOrder $workOrder): void
    {
        $template = ChecklistTemplate::query()
            ->where('work_order_type', $workOrder->type)
            ->where('is_active', true)
            ->with('items')
            ->first();

        if ($template === null) {
            return;
        }

        foreach ($template->items as $item) {
            $workOrder->checklistItems()->create([
                'position' => $item->position,
                'label' => $item->label,
            ]);
        }
    }
}
