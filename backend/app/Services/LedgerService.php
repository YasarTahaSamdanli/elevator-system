<?php

namespace App\Services;

use App\Models\AccountTransaction;
use App\Models\Elevator;
use App\Models\Scopes\CompanyScope;
use App\Models\ServiceContract;
use App\Models\WorkOrder;
use Carbon\CarbonInterface;

class LedgerService
{
    /**
     * Charge the customer for the completed work order's material lines.
     * Line total uses the sale price snapshot; lines without a sale price
     * fall back to the cost snapshot so the charge is never silently lost.
     * repair work orders post as revision_charge, everything
     * else as part_charge (mirrors the customer's Parça/Revizyon split).
     *
     * Runs inside the completion transaction (after the stock issue) and is
     * idempotent per work order.
     */
    public function chargeForCompletion(WorkOrder $workOrder): void
    {
        $alreadyCharged = AccountTransaction::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('work_order_id', $workOrder->id)
            ->whereIn('type', ['part_charge', 'revision_charge'])
            ->exists();

        if ($alreadyCharged) {
            return;
        }

        $total = 0.0;

        foreach ($workOrder->items()->get() as $item) {
            $unitPrice = $item->sale_unit_price ?? $item->unit_price;

            if ($unitPrice !== null) {
                $total += (float) $item->quantity * (float) $unitPrice;
            }
        }

        if ($total <= 0) {
            return;
        }

        // Query without the company scope: completion may run in queued or
        // console contexts where no user is authenticated.
        $contract = ServiceContract::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->findOrFail($workOrder->service_contract_id);
        $elevator = Elevator::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->findOrFail($contract->elevator_id);

        $transaction = new AccountTransaction;
        $transaction->forceFill([
            'company_id' => $workOrder->company_id,
            'building_id' => $elevator->building_id,
            'elevator_id' => $elevator->id,
            'service_contract_id' => $contract->id,
            'type' => $workOrder->type === 'repair'
                ? 'revision_charge'
                : 'part_charge',
            'amount' => number_format($total, 2, '.', ''),
            'occurred_at' => $workOrder->completed_at ?? now(),
            'work_order_id' => $workOrder->id,
            'description' => "İş emri malzeme bedeli: {$workOrder->work_order_number}",
        ])->save();
    }

    /**
     * Post the month's maintenance fee for every active contract with a
     * monthly_fee, dated to the first of the month. Idempotent: one accrual
     * per contract per month (keyed on service_contract_id + occurred_at).
     * Runs across all companies — console/scheduler context.
     *
     * @return int number of accruals created
     */
    public function accrueMonthlyFees(CarbonInterface $month): int
    {
        $monthStart = $month->toImmutable()->startOfMonth();
        $created = 0;

        $contracts = ServiceContract::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('status', 'active')
            ->whereNotNull('monthly_fee')
            ->where('monthly_fee', '>', 0)
            ->whereDate('start_date', '<=', $monthStart->endOfMonth())
            ->where(function ($query) use ($monthStart): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $monthStart);
            })
            ->get();

        foreach ($contracts as $contract) {
            $exists = AccountTransaction::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->where('service_contract_id', $contract->id)
                ->where('type', 'maintenance_fee')
                ->whereDate('occurred_at', $monthStart)
                ->exists();

            if ($exists) {
                continue;
            }

            $elevator = Elevator::query()
                ->withoutGlobalScope(CompanyScope::class)
                ->find($contract->elevator_id);

            if ($elevator === null) {
                continue;
            }

            $transaction = new AccountTransaction;
            $transaction->forceFill([
                'company_id' => $contract->company_id,
                'building_id' => $elevator->building_id,
                'elevator_id' => $elevator->id,
                'service_contract_id' => $contract->id,
                'type' => 'maintenance_fee',
                'amount' => $contract->monthly_fee,
                'occurred_at' => $monthStart,
                'description' => sprintf('%s bakım ücreti', $monthStart->locale('tr')->translatedFormat('F Y')),
            ])->save();

            $created++;
        }

        return $created;
    }
}
