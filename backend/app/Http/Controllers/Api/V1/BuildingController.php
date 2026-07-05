<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BuildingController extends Controller
{
    public function index(): JsonResponse
    {
        $buildings = Building::query()->latest()->get();

        return ApiResponse::success(
            data: BuildingResource::collection($buildings),
        );
    }

    public function show(Building $building): JsonResponse
    {
        return ApiResponse::success(
            data: new BuildingResource($building),
        );
    }

    public function store(StoreBuildingRequest $request): JsonResponse
    {
        $building = Building::create($request->validated());

        return ApiResponse::success(
            data: new BuildingResource($building),
            message: 'Building created successfully.',
            status: 201,
        );
    }

    public function update(UpdateBuildingRequest $request, Building $building): JsonResponse
    {
        $building->update($request->validated());

        return ApiResponse::success(
            data: new BuildingResource($building->fresh()),
            message: 'Building updated successfully.',
        );
    }

    public function destroy(Building $building): JsonResponse
    {
        $building->delete();

        return ApiResponse::success(
            message: 'Building deleted successfully.',
        );
    }
}
