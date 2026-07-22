<?php

namespace App\Services;

use App\Models\ElevatorInspection;
use App\Models\InspectionFinding;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InspectionWorkOrderService
{
    /**
     * Open a repair work order for the inspection's unresolved findings.
     * The findings are copied onto the work order as checklist items
     * (snapshots — the checklist template for the type is deliberately
     * skipped: the inspection report *is* the checklist here).
     */
    public function createFor(ElevatorInspection $inspection): WorkOrder
    {
        if ($inspection->work_order_id !== null) {
            throw ValidationException::withMessages([
                'work_order' => ['A work order has already been created for this inspection.'],
            ]);
        }

        $serviceContract = $inspection->elevator
            ->serviceContracts()
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->first();

        if ($serviceContract === null) {
            throw ValidationException::withMessages([
                'work_order' => ['The elevator has no active service contract to attach a work order to.'],
            ]);
        }

        return DB::transaction(function () use ($inspection, $serviceContract): WorkOrder {
            $workOrder = new WorkOrder([
                'service_contract_id' => $serviceContract->id,
                'type' => 'repair',
                'status' => 'draft',
                'priority' => match ($inspection->label) {
                    'red' => 'critical',
                    'yellow' => 'high',
                    default => 'normal',
                },
                'description' => trim(sprintf(
                    'Remediation of inspection findings %s',
                    $inspection->report_number ? "(report {$inspection->report_number})" : '',
                )),
            ]);
            // Explicit: this also runs from the report import console pipeline,
            // where there is no authenticated user to derive the company from.
            $workOrder->company_id = $serviceContract->company_id;
            $workOrder->save();

            $position = 1;

            // Present the checklist exactly like the paper report: red
            // section first, then yellow, then blue, each in report order.
            // Hand-entered findings without a severity go to the end.
            $findings = $inspection->findings()
                ->where('is_resolved', false)
                ->get()
                ->sortBy(fn (InspectionFinding $finding) => [
                    InspectionFinding::SEVERITY_ORDER[$finding->severity] ?? count(InspectionFinding::SEVERITY_ORDER),
                    $finding->position ?? PHP_INT_MAX,
                    $finding->id,
                ])
                ->values();

            foreach ($findings as $finding) {
                $item = $workOrder->checklistItems()->make([
                    'position' => $position++,
                    'label' => $finding->description,
                    'severity' => $finding->severity,
                    'item_code' => $finding->item_code,
                ]);
                $item->company_id = $workOrder->company_id;
                $item->save();
            }

            $inspection->update(['work_order_id' => $workOrder->id]);

            return $workOrder;
        });
    }
}
