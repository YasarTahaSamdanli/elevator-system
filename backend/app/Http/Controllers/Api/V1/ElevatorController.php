<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Elevator\StoreElevatorRequest;
use App\Http\Requests\Elevator\UpdateElevatorRequest;
use App\Http\Resources\ElevatorResource;
use App\Models\Building;
use App\Models\Elevator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ElevatorController extends Controller
{
    public function index(): JsonResponse
    {
        $elevators = Elevator::query()->with('building')->latest()->get();

        return ApiResponse::success(
            data: ElevatorResource::collection($elevators),
        );
    }

    public function show(Elevator $elevator): JsonResponse
    {
        return ApiResponse::success(
            data: new ElevatorResource($elevator->load('building')),
        );
    }

    public function store(StoreElevatorRequest $request): JsonResponse
    {
        $data = $request->validated();
        $building = Building::where('uuid', $data['building_uuid'])->firstOrFail();
        unset($data['building_uuid']);
        $data['building_id'] = $building->id;

        $elevator = Elevator::create($data);

        return ApiResponse::success(
            data: new ElevatorResource($elevator->load('building')),
            message: 'Elevator created successfully.',
            status: 201,
        );
    }

    public function update(UpdateElevatorRequest $request, Elevator $elevator): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('building_uuid', $data)) {
            $building = Building::where('uuid', $data['building_uuid'])->firstOrFail();
            unset($data['building_uuid']);
            $data['building_id'] = $building->id;
        }

        $elevator->update($data);

        return ApiResponse::success(
            data: new ElevatorResource($elevator->fresh()->load('building')),
            message: 'Elevator updated successfully.',
        );
    }

    public function destroy(Elevator $elevator): JsonResponse
    {
        $elevator->delete();

        return ApiResponse::success(
            message: 'Elevator deleted successfully.',
        );
    }
}
