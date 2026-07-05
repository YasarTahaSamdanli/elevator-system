<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Building\StoreBuildingRequest;
use App\Http\Requests\Building\UpdateBuildingRequest;
use App\Http\Resources\BuildingResource;
use App\Models\Building;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $buildings = ListQuery::for(Building::query(), $request)
            ->filterable(['city', 'district', 'is_active'])
            ->searchable(['name', 'code', 'city', 'district', 'manager_name'])
            ->sortable(['name', 'code', 'city', 'district', 'created_at', 'updated_at'])
            ->dateRange('created_at')
            ->paginate();

        return ApiResponse::paginated($buildings, BuildingResource::class);
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
