<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderItemRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderItemRequest;
use App\Http\Resources\WorkOrderItemResource;
use App\Models\Material;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class WorkOrderItemController extends Controller
{
    public function store(StoreWorkOrderItemRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $this->ensureItemsAreMutable($workOrder);

        $data = $request->validated();
        $material = Material::where('uuid', $data['material_uuid'])->firstOrFail();

        $item = $workOrder->items()->create([
            'material_id' => $material->id,
            'quantity' => $data['quantity'],
            'unit_price' => array_key_exists('unit_price', $data) ? $data['unit_price'] : $material->default_unit_price,
            'sale_unit_price' => array_key_exists('sale_unit_price', $data) ? $data['sale_unit_price'] : $material->default_sale_price,
            'note' => $data['note'] ?? null,
        ]);

        return ApiResponse::success(
            data: new WorkOrderItemResource($item->load('material')),
            message: 'Work order item created successfully.',
            status: 201,
        );
    }

    public function update(UpdateWorkOrderItemRequest $request, WorkOrder $workOrder, WorkOrderItem $item): JsonResponse
    {
        abort_unless($item->work_order_id === $workOrder->id, 404);
        $this->ensureItemsAreMutable($workOrder);

        $item->update($request->validated());

        return ApiResponse::success(
            data: new WorkOrderItemResource($item->fresh()->load('material')),
            message: 'Work order item updated successfully.',
        );
    }

    public function destroy(WorkOrder $workOrder, WorkOrderItem $item): JsonResponse
    {
        abort_unless($item->work_order_id === $workOrder->id, 404);
        $this->ensureItemsAreMutable($workOrder);

        $item->delete();

        return ApiResponse::success(message: 'Work order item deleted successfully.');
    }

    /**
     * Completed work orders have already issued their stock movements and
     * the ledger is immutable, so their material lines are frozen too.
     */
    private function ensureItemsAreMutable(WorkOrder $workOrder): void
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'work_order' => ["Materials cannot be modified on a {$workOrder->status} work order."],
            ]);
        }
    }
}
