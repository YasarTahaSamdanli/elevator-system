<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\WorkOrderResource;
use App\Models\Elevator;
use App\Models\InspectionImport;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\StockMovement;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** @var list<string> */
    private const OPEN_STATUSES = ['draft', 'planned', 'assigned', 'in_progress'];

    public function __invoke(Request $request): JsonResponse
    {
        $today = CarbonImmutable::today();
        $thisMonthStart = $today->startOfMonth();
        $thisMonthEnd = $today->endOfMonth();
        $lastMonthStart = $today->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = $today->subMonthNoOverflow()->endOfMonth();

        $materials = $this->materialsWithStock()
            ->where('is_active', true)
            ->orderBy('code')
            ->limit(100)
            ->get();
        $monthlyOut = StockMovement::query()
            ->where('type', 'work_order_out')
            ->whereDate('occurred_at', '>=', $thisMonthStart)
            ->whereDate('occurred_at', '<=', $thisMonthEnd)
            ->limit(100)
            ->get();

        $stats = [
            'activeElevators' => Elevator::query()->where('status', 'active')->count(),
            'openWorkOrders' => WorkOrder::query()->whereIn('status', self::OPEN_STATUSES)->count(),
            'completedThisMonth' => $this->completedWorkOrders($thisMonthStart, $thisMonthEnd)->count(),
            'completedLastMonth' => $this->completedWorkOrders($lastMonthStart, $lastMonthEnd)->count(),
            'expiringContracts' => ServiceContract::query()
                ->where('status', 'active')
                ->whereDate('end_date', '>=', $today)
                ->whereDate('end_date', '<=', $today->addDays(30))
                ->count(),
            'inventoryValue' => $materials->sum(
                fn (Material $material): float => (float) $material->stock_on_hand * (float) ($material->default_unit_price ?? 0),
            ),
            'lowStockMaterials' => $materials
                ->filter(fn (Material $material): bool => (float) $material->stock_on_hand < (float) $material->min_stock_level)
                ->count(),
            'monthlyConsumptionValue' => $monthlyOut->sum(
                fn (StockMovement $movement): float => (float) $movement->quantity * (float) ($movement->unit_price ?? 0),
            ),
            'stockMovementCount' => StockMovement::query()->count(),
        ];

        return ApiResponse::success([
            'stats' => $stats,
            'operations' => $this->operations($today, $thisMonthStart, $thisMonthEnd),
            'volume' => $this->workOrderVolume($today, 30),
            'inventoryMovement' => $this->inventoryMovement($today, 30),
            'distribution' => $this->typeDistribution($today, 90),
            'openWorkOrders' => WorkOrderResource::collection($this->topOpenWorkOrders())->resolve($request),
            'activity' => $this->recentActivity(),
            'lowStockMaterials' => MaterialResource::collection(
                $materials
                    ->filter(fn (Material $material): bool => (float) $material->stock_on_hand < (float) $material->min_stock_level)
                    ->take(5)
                    ->values(),
            )->resolve($request),
            'topConsumedMaterials' => $this->topConsumedMaterials($today, 5, 90),
            'recentStockMovements' => StockMovementResource::collection($this->recentStockMovements())->resolve($request),
        ]);
    }

    private function operations(CarbonImmutable $today, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        return [
            'maintenance' => [
                'open' => $this->openWorkOrdersOfType('maintenance'),
                'completedThisMonth' => $this->completedWorkOrders($monthStart, $monthEnd, 'maintenance')->count(),
                'scheduledToday' => WorkOrder::query()
                    ->where('type', 'maintenance')
                    ->whereIn('status', self::OPEN_STATUSES)
                    ->whereDate('scheduled_at', $today)
                    ->count(),
            ],
            'fault' => [
                'open' => $this->openWorkOrdersOfType('fault'),
                'completedThisMonth' => $this->completedWorkOrders($monthStart, $monthEnd, 'fault')->count(),
            ],
            'revision' => [
                'open' => $this->openWorkOrdersOfType('repair'),
                'redLabeled' => Elevator::query()->where('current_label', 'red')->count(),
                'yellowLabeled' => Elevator::query()->where('current_label', 'yellow')->count(),
            ],
            'inspection' => [
                'dueThisMonth' => Elevator::query()
                    ->whereDate('next_inspection_due', '>=', $monthStart)
                    ->whereDate('next_inspection_due', '<=', $monthEnd)
                    ->count(),
                'followUpSoon' => Elevator::query()
                    ->whereDate('follow_up_due', '<=', $today->addDays(15))
                    ->count(),
                'reportsToReview' => InspectionImport::query()->where('status', 'needs_review')->count(),
            ],
        ];
    }

    private function workOrderVolume(CarbonImmutable $today, int $days): array
    {
        $from = $today->subDays($days - 1);
        $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $counts[$from->addDays($i)->toDateString()] = 0;
        }

        WorkOrder::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $today)
            ->get(['created_at'])
            ->each(function (WorkOrder $workOrder) use (&$counts): void {
                $day = $workOrder->created_at->toDateString();
                if (array_key_exists($day, $counts)) {
                    $counts[$day]++;
                }
            });

        return collect($counts)->map(fn (int $value, string $x): array => compact('x', 'value'))->values()->all();
    }

    private function inventoryMovement(CarbonImmutable $today, int $days): array
    {
        $from = $today->subDays($days - 1);
        $rows = [];
        for ($i = 0; $i < $days; $i++) {
            $key = $from->addDays($i)->toDateString();
            $rows[$key] = ['x' => $key, 'inValue' => 0.0, 'outValue' => 0.0];
        }

        StockMovement::query()
            ->whereDate('occurred_at', '>=', $from)
            ->whereDate('occurred_at', '<=', $today)
            ->orderBy('occurred_at')
            ->limit(100)
            ->get()
            ->each(function (StockMovement $movement) use (&$rows): void {
                $day = $movement->occurred_at->toDateString();
                if (! array_key_exists($day, $rows)) {
                    return;
                }

                $value = (float) $movement->quantity * (float) ($movement->unit_price ?? 0);
                if ($movement->signedQuantity() >= 0) {
                    $rows[$day]['inValue'] += $value;
                } else {
                    $rows[$day]['outValue'] += $value;
                }
            });

        return array_values($rows);
    }

    private function typeDistribution(CarbonImmutable $today, int $days): array
    {
        $labels = [
            'maintenance' => 'Bakim',
            'fault' => 'Ariza',
            'inspection' => 'Muayene',
            'repair' => 'Revizyon',
        ];
        $from = $today->subDays($days);

        return collect($labels)
            ->map(fn (string $label, string $type): array => [
                'type' => $type,
                'label' => $label,
                'value' => WorkOrder::query()->where('type', $type)->whereDate('created_at', '>=', $from)->count(),
            ])
            ->filter(fn (array $row): bool => $row['value'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, WorkOrder>
     */
    private function topOpenWorkOrders(): Collection
    {
        $priorityRank = ['critical' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];

        return WorkOrder::query()
            ->with(['serviceContract.elevator.building', 'assignedUser'])
            ->whereIn('status', self::OPEN_STATUSES)
            ->orderByDesc('scheduled_at')
            ->limit(25)
            ->get()
            ->sortBy(fn (WorkOrder $workOrder): int => $priorityRank[$workOrder->priority] ?? 99)
            ->take(5)
            ->values();
    }

    private function recentActivity(): array
    {
        return WorkOrder::query()
            ->with(['serviceContract.elevator.building', 'assignedUser'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (WorkOrder $workOrder): array {
                $elevator = $workOrder->serviceContract?->elevator;
                $target = ($elevator?->building?->name ?? '-') . ' · ' . ($elevator?->name ?? $elevator?->serial_number ?? '-');

                if ($workOrder->status === 'completed') {
                    return $this->activityItem($workOrder, 'is emri tamamlandi', $target, $workOrder->updated_at, 'completed');
                }
                if ($workOrder->status === 'cancelled') {
                    return $this->activityItem($workOrder, 'is emri iptal edildi', $target, $workOrder->updated_at, 'cancelled');
                }
                if ($workOrder->status === 'in_progress') {
                    return $this->activityItem($workOrder, 'uzerinde calisiliyor', $target, $workOrder->updated_at, 'progress');
                }
                if ($workOrder->assignedUser) {
                    return $this->activityItem($workOrder, $workOrder->assignedUser->name . ' atandi', $target, $workOrder->updated_at, 'assigned');
                }

                return $this->activityItem($workOrder, 'is emri olusturuldu', $target, $workOrder->created_at, 'created');
            })
            ->all();
    }

    private function activityItem(WorkOrder $workOrder, string $message, string $target, mixed $at, string $kind): array
    {
        return [
            'id' => $workOrder->uuid,
            'message' => $message,
            'target' => $target,
            'at' => $at,
            'kind' => $kind,
        ];
    }

    private function topConsumedMaterials(CarbonImmutable $today, int $limit, int $days): array
    {
        return StockMovement::query()
            ->with('material')
            ->where('type', 'work_order_out')
            ->whereDate('occurred_at', '>=', $today->subDays($days))
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get()
            ->groupBy('material_id')
            ->map(function (Collection $movements): array {
                /** @var StockMovement $first */
                $first = $movements->first();

                return [
                    'material' => [
                        'uuid' => $first->material?->uuid,
                        'code' => $first->material?->code,
                        'name' => $first->material?->name,
                        'unit' => $first->material?->unit,
                    ],
                    'quantity' => $movements->sum(fn (StockMovement $movement): float => (float) $movement->quantity),
                    'value' => $movements->sum(
                        fn (StockMovement $movement): float => (float) $movement->quantity * (float) ($movement->unit_price ?? 0),
                    ),
                ];
            })
            ->sortByDesc('value')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, StockMovement>
     */
    private function recentStockMovements(): Collection
    {
        return StockMovement::query()
            ->with(['material', 'warehouse', 'workOrder', 'creator'])
            ->orderByDesc('occurred_at')
            ->limit(6)
            ->get();
    }

    private function openWorkOrdersOfType(string $type): int
    {
        return WorkOrder::query()
            ->where('type', $type)
            ->whereIn('status', self::OPEN_STATUSES)
            ->count();
    }

    private function completedWorkOrders(CarbonImmutable $from, CarbonImmutable $to, ?string $type = null): \Illuminate\Database\Eloquent\Builder
    {
        return WorkOrder::query()
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->where('status', 'completed')
            ->whereDate('completed_at', '>=', $from)
            ->whereDate('completed_at', '<=', $to);
    }

    private function materialsWithStock(): \Illuminate\Database\Eloquent\Builder
    {
        $outboundTypes = "'".implode("', '", StockMovement::OUTBOUND_TYPES)."'";
        $signedStock = "COALESCE(SUM(CASE WHEN stock_movements.type IN ($outboundTypes) THEN -stock_movements.quantity ELSE stock_movements.quantity END), 0)";

        return Material::query()
            ->leftJoin('stock_movements', 'materials.id', '=', 'stock_movements.material_id')
            ->select('materials.*')
            ->selectRaw("$signedStock as stock_on_hand")
            ->groupBy('materials.id');
    }
}
