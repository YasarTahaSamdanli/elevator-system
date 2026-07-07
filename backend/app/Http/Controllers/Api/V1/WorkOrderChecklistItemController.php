<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\UpdateWorkOrderChecklistItemRequest;
use App\Http\Resources\WorkOrderChecklistItemResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WorkOrderChecklistItemController extends Controller
{
    public function update(
        UpdateWorkOrderChecklistItemRequest $request,
        WorkOrder $workOrder,
        WorkOrderChecklistItem $checklistItem,
    ): JsonResponse {
        $checklistItem->update($request->validated());

        return ApiResponse::success(
            data: new WorkOrderChecklistItemResource($checklistItem->fresh()),
            message: 'Checklist item updated successfully.',
        );
    }
}
