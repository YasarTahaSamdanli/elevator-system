<?php

namespace App\Services;

use App\Models\Scopes\CompanyScope;
use App\Models\ServiceContract;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;

class MaintenanceWorkOrderService
{
    /**
     * Open the month's maintenance work order for every service contract
     * that is active during the given month. Idempotent: a contract that
     * already has a maintenance work order scheduled within the month
     * (including cancelled or soft-deleted ones) is skipped, so manually
     * created or intentionally removed orders are never duplicated.
     *
     * Runs unauthenticated (scheduler/console), so company scoping is
     * bypassed and company_id is copied from the contract explicitly.
     *
     * @return int number of work orders created
     */
    public function generateForMonth(CarbonInterface $month): int
    {
        $monthStart = $month->toImmutable()->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $created = 0;

        $contracts = ServiceContract::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $monthEnd)
            ->where(function ($query) use ($monthStart): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $monthStart);
            })
            ->get();

        foreach ($contracts as $contract) {
            $exists = WorkOrder::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->withTrashed()
                ->where('service_contract_id', $contract->id)
                ->where('type', 'maintenance')
                ->whereDate('scheduled_at', '>=', $monthStart)
                ->whereDate('scheduled_at', '<=', $monthEnd)
                ->exists();

            if ($exists) {
                continue;
            }

            $workOrder = new WorkOrder;
            $workOrder->forceFill([
                'company_id' => $contract->company_id,
                'service_contract_id' => $contract->id,
                'type' => 'maintenance',
                'status' => 'planned',
                'priority' => 'normal',
                'scheduled_at' => $monthStart,
                'description' => sprintf('%s periyodik bakım', $monthStart->locale('tr')->translatedFormat('F Y')),
            ])->save();

            $created++;
        }

        return $created;
    }
}
