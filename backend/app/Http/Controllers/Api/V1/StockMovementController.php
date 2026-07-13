<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovement\StoreStockMovementRequest;
use App\Http\Requests\StockMovement\TransferStockRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Material;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $movements = ListQuery::for(StockMovement::query()->with(['material', 'warehouse', 'workOrder', 'creator']), $request)
            ->filterable([
                'type',
                'material_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'material',
                    fn (Builder $material) => $material->where('uuid', $value),
                ),
                'warehouse_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'warehouse',
                    fn (Builder $warehouse) => $warehouse->where('uuid', $value),
                ),
            ])
            ->searchable([
                'note',
                'material' => ['code', 'name', 'category', 'unit'],
                'warehouse' => ['name', 'type'],
                'workOrder' => ['work_order_number', 'description', 'notes'],
                'creator' => ['name', 'email', 'phone'],
            ])
            ->sortable(['type', 'quantity', 'unit_price', 'occurred_at', 'created_at'])
            ->dateRange('occurred_at', 'created_at')
            ->paginate();

        return ApiResponse::paginated($movements, StockMovementResource::class);
    }

    public function show(StockMovement $stockMovement): JsonResponse
    {
        return ApiResponse::success(
            data: new StockMovementResource($stockMovement->load(['material', 'warehouse', 'workOrder', 'creator'])),
        );
    }

    public function store(StoreStockMovementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $material = Material::where('uuid', $data['material_uuid'])->firstOrFail();
        $warehouse = Warehouse::where('uuid', $data['warehouse_uuid'])->firstOrFail();
        $workOrder = isset($data['work_order_uuid'])
            ? WorkOrder::where('uuid', $data['work_order_uuid'])->firstOrFail()
            : null;

        $movement = StockMovement::create([
            'material_id' => $material->id,
            'warehouse_id' => $warehouse->id,
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'] ?? null,
            'work_order_id' => $workOrder?->id,
            'occurred_at' => $data['occurred_at'] ?? now(),
            'note' => $data['note'] ?? null,
        ]);

        if (($data['update_material_price'] ?? false) && $data['type'] === 'purchase_in' && array_key_exists('unit_price', $data)) {
            $material->update(['default_unit_price' => $data['unit_price']]);
        }

        return ApiResponse::success(
            data: new StockMovementResource($movement->load(['material', 'warehouse', 'workOrder', 'creator'])),
            message: 'Stock movement created successfully.',
            status: 201,
        );
    }

    /**
     * Create both legs of a warehouse transfer atomically so the ledger can
     * never hold a half-finished transfer.
     */
    public function transfer(TransferStockRequest $request): JsonResponse
    {
        $data = $request->validated();
        $material = Material::where('uuid', $data['material_uuid'])->firstOrFail();
        $fromWarehouse = Warehouse::where('uuid', $data['from_warehouse_uuid'])->firstOrFail();
        $toWarehouse = Warehouse::where('uuid', $data['to_warehouse_uuid'])->firstOrFail();

        $shared = [
            'material_id' => $material->id,
            'quantity' => $data['quantity'],
            'transfer_group_uuid' => (string) Str::uuid(),
            'occurred_at' => $data['occurred_at'] ?? now(),
            'note' => $data['note'] ?? null,
        ];

        $movements = DB::transaction(fn (): array => [
            StockMovement::create($shared + ['type' => 'transfer_out', 'warehouse_id' => $fromWarehouse->id]),
            StockMovement::create($shared + ['type' => 'transfer_in', 'warehouse_id' => $toWarehouse->id]),
        ]);

        return ApiResponse::success(
            data: StockMovementResource::collection(
                collect($movements)->each->load(['material', 'warehouse', 'creator']),
            ),
            message: 'Stock transfer created successfully.',
            status: 201,
        );
    }
}
