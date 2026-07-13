<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElevatorInspection\UpdateInspectionFindingRequest;
use App\Http\Resources\InspectionFindingResource;
use App\Models\ElevatorInspection;
use App\Models\InspectionFinding;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InspectionFindingController extends Controller
{
    public function update(
        UpdateInspectionFindingRequest $request,
        ElevatorInspection $elevatorInspection,
        InspectionFinding $finding,
    ): JsonResponse {
        $finding->update($request->validated());

        return ApiResponse::success(
            data: new InspectionFindingResource($finding->fresh()),
            message: 'Inspection finding updated successfully.',
        );
    }
}
