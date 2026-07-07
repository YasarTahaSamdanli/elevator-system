<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\ServiceContract;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderChecklistService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ])
            ->searchable(['work_order_number', 'description'])
            ->sortable(['work_order_number', 'status', 'type', 'priority', 'scheduled_at', 'started_at', 'completed_at', 'created_at', 'updated_at'])
            ->dateRange('scheduled_at', 'completed_at', 'created_at')
            ->paginate();

        return ApiResponse::paginated($workOrders, WorkOrderResource::class);
    }

    public function show(WorkOrder $workOrder): JsonResponse
    {
        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems'])),
        );
    }

    public function store(StoreWorkOrderRequest $request, WorkOrderChecklistService $checklistService): JsonResponse
    {
        $data = $request->validated();

        $serviceContract = ServiceContract::where('uuid', $data['service_contract_uuid'])->firstOrFail();
        unset($data['service_contract_uuid']);
        $data['service_contract_id'] = $serviceContract->id;

        if (array_key_exists('assigned_user_uuid', $data)) {
            $data['assigned_user_id'] = $this->resolveAssignedUserId($data['assigned_user_uuid']);
            unset($data['assigned_user_uuid']);
        }

        $workOrder = WorkOrder::create($data);
        $checklistService->applyTemplate($workOrder);

        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems'])),
            message: 'Work order created successfully.',
            status: 201,
        );
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $data = $request->validated();

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

        if ($status === 'in_progress' && ! array_key_exists('started_at', $data) && $workOrder->started_at === null) {
            $data['started_at'] = now();
        }

        if ($status === 'completed' && ! array_key_exists('completed_at', $data) && $workOrder->completed_at === null) {
            $data['completed_at'] = now();
        }

        $workOrder->update($data);

        return ApiResponse::success(
            data: new WorkOrderResource($workOrder->fresh()->load(['serviceContract.elevator.building', 'assignedUser', 'checklistItems'])),
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

    private function resolveAssignedUserId(?string $assignedUserUuid): ?int
    {
        if ($assignedUserUuid === null) {
            return null;
        }

        return User::where('uuid', $assignedUserUuid)->firstOrFail()->id;
    }
}
