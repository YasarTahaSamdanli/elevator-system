<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Material;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\LedgerService;
use App\Services\WorkOrderChecklistService;
use App\Services\WorkOrderStockService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workOrders = ListQuery::for(WorkOrder::query()->with(['serviceContract.elevator.building', 'assignedUser']), $request)
            ->filterable([
                'status',
                'type',
                'priority',
                'assigned_user_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'assignedUser',
                    fn (Builder $user) => $user->where('uuid', $value),
                ),
                'service_contract_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'serviceContract',
                    fn (Builder $contract) => $contract->where('uuid', $value),
                ),
                // QR akışı: okutulan asansörün tüm iş emirleri.
                'elevator_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'serviceContract.elevator',
                    fn (Builder $elevator) => $elevator->where('uuid', $value),
                ),
            ])
            ->searchable([
                'work_order_number',
                'description',
                'notes',
                'assignedUser' => ['name', 'email', 'phone'],
                'serviceContract' => ['contract_number', 'notes'],
                'serviceContract.elevator' => ['serial_number', 'qr_identifier', 'name', 'manufacturer', 'model', 'registration_number'],
                'serviceContract.elevator.building' => ['name', 'code', 'address', 'city', 'district', 'manager_name', 'manager_phone'],
            ])
            ->sortable(['work_order_number', 'status', 'type', 'priority', 'scheduled_at', 'started_at', 'completed_at', 'created_at', 'updated_at'])
            ->dateRange('scheduled_at', 'completed_at', 'created_at')
            ->paginate();

        return ApiResponse::paginated($workOrders, WorkOrderResource::class);
    }

    /**
     * Dashboard counters for the mobile technician home screen. Counted in
     * the database so they stay correct regardless of list pagination. The
     * client sends its local date so "today" follows the device timezone.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();

        return ApiResponse::success(data: [
            'date' => $date,
            // Today's workload: everything scheduled for the day except
            // cancelled orders (they no longer need a visit).
            'scheduled_today' => WorkOrder::whereDate('scheduled_at', $date)
                ->where('status', '!=', 'cancelled')
                ->count(),
            'assigned' => WorkOrder::where('status', 'assigned')->count(),
            'in_progress' => WorkOrder::where('status', 'in_progress')->count(),
            // Finished today by completion time, independent of when the
            // order was originally scheduled.
            'completed_today' => WorkOrder::where('status', 'completed')
                ->whereDate('completed_at', $date)
                ->count(),
        ]);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems', 'items'])),
        );
    }

    public function store(StoreWorkOrderRequest $request, WorkOrderChecklistService $checklistService): JsonResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $serviceContract = ServiceContract::where('uuid', $data['service_contract_uuid'])->firstOrFail();
        unset($data['service_contract_uuid']);
        $data['service_contract_id'] = $serviceContract->id;

        if (array_key_exists('assigned_user_uuid', $data)) {
            $data['assigned_user_id'] = $this->resolveAssignedUserId($data['assigned_user_uuid']);
            unset($data['assigned_user_uuid']);
        }

        $workOrder = DB::transaction(function () use ($data, $items, $checklistService): WorkOrder {
            $workOrder = WorkOrder::create($data);
            $checklistService->applyTemplate($workOrder);
            $this->syncItems($workOrder, $items);

            return $workOrder;
        });

        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems', 'items'])),
            message: 'Work order created successfully.',
            status: 201,
        );
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, WorkOrderStockService $stockService, LedgerService $ledgerService): JsonResponse
    {
        $data = $request->validated();
        $items = array_key_exists('items', $data) ? $data['items'] : null;
        // qr_identifier is proof-of-presence input, not a work order column.
        unset($data['items'], $data['qr_identifier']);

        if (array_key_exists('service_contract_uuid', $data)) {
            $serviceContract = ServiceContract::where('uuid', $data['service_contract_uuid'])->firstOrFail();
            unset($data['service_contract_uuid']);
            $data['service_contract_id'] = $serviceContract->id;
        }

        if (array_key_exists('assigned_user_uuid', $data)) {
            $data['assigned_user_id'] = $this->resolveAssignedUserId($data['assigned_user_uuid']);
            unset($data['assigned_user_uuid']);
        }

        // Lifecycle timestamps follow the status when the client doesn't
        // supply them explicitly.
        $status = $data['status'] ?? null;

        // The technician who actually starts the job takes over the
        // assignment (unless the request assigns someone explicitly).
        // Office roles start on behalf of others, so they are exempt.
        if ($status === 'in_progress'
            && $workOrder->status !== 'in_progress'
            && ! array_key_exists('assigned_user_id', $data)
            && $request->user()->hasRole('Technician')) {
            $data['assigned_user_id'] = $request->user()->id;
        }

        if ($status === 'in_progress' && ! array_key_exists('started_at', $data) && $workOrder->started_at === null) {
            $data['started_at'] = now();
        }

        if ($status === 'completed' && ! array_key_exists('completed_at', $data) && $workOrder->completed_at === null) {
            $data['completed_at'] = now();
        }

        DB::transaction(function () use ($workOrder, $data, $items, $status, $stockService, $ledgerService): void {
            // Serialize concurrent completion requests on the work order row
            // so the service's "already issued" check cannot race and deduct
            // stock twice.
            $lockedWorkOrder = WorkOrder::whereKey($workOrder->id)->lockForUpdate()->firstOrFail();

            if ($items !== null) {
                $this->ensureItemsAreMutable($lockedWorkOrder);
            }

            $workOrder->update($data);

            if ($items !== null) {
                $this->syncItems($workOrder->fresh(), $items);
            }

            if ($status === 'completed') {
                $completedWorkOrder = $workOrder->fresh();
                $stockService->issueMaterialsForCompletion($completedWorkOrder);
                // Stock deduction (cost) and the customer charge (revenue)
                // must land or fail together.
                $ledgerService->chargeForCompletion($completedWorkOrder);
            }
        });

        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->fresh()->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems', 'items'])),
            message: 'Work order updated successfully.',
        );
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $workOrder->delete();

        return ApiResponse::success(
            message: 'Work order deleted successfully.',
        );
    }

    /**
     * Replace the work order's material lines with the given payload;
     * `items` always carries the full desired set, not a delta.
     */
    private function syncItems(WorkOrder $workOrder, array $items): void
    {
        $workOrder->items()->delete();

        foreach ($items as $item) {
            $material = Material::where('uuid', $item['material_uuid'])->firstOrFail();

            $workOrder->items()->create([
                'material_id' => $material->id,
                'quantity' => $item['quantity'],
                'unit_price' => array_key_exists('unit_price', $item) ? $item['unit_price'] : $material->default_unit_price,
                'sale_unit_price' => array_key_exists('sale_unit_price', $item) ? $item['sale_unit_price'] : $material->default_sale_price,
                'note' => $item['note'] ?? null,
            ]);
        }
    }

    /**
     * Completed work orders have already issued their stock movements and
     * the ledger is immutable, so their material lines are frozen too
     * (mirrors WorkOrderItemController::ensureItemsAreMutable).
     */
    private function ensureItemsAreMutable(WorkOrder $workOrder): void
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'items' => ["Materials cannot be modified on a {$workOrder->status} work order."],
            ]);
        }
    }

    private function resolveAssignedUserId(?string $assignedUserUuid): ?int
    {
        if ($assignedUserUuid === null) {
            return null;
        }

        return User::where('uuid', $assignedUserUuid)->firstOrFail()->id;
    }
}
