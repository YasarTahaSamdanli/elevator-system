<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceContract\StoreServiceContractRequest;
use App\Http\Requests\ServiceContract\UpdateServiceContractRequest;
use App\Http\Resources\ServiceContractResource;
use App\Models\Elevator;
use App\Models\ServiceContract;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ServiceContractController extends Controller
{
    public function index(): JsonResponse
    {
        $serviceContracts = ServiceContract::query()->with('elevator')->latest()->get();

        return ApiResponse::success(
            data: ServiceContractResource::collection($serviceContracts),
        );
    }

    public function show(ServiceContract $serviceContract): JsonResponse
    {
        return ApiResponse::success(
            data: new ServiceContractResource($serviceContract->load('elevator')),
        );
    }

    public function store(StoreServiceContractRequest $request): JsonResponse
    {
        $data = $request->validated();
        $elevator = Elevator::where('uuid', $data['elevator_uuid'])->firstOrFail();
        unset($data['elevator_uuid']);
        $data['elevator_id'] = $elevator->id;

        $serviceContract = ServiceContract::create($data);

        return ApiResponse::success(
            data: new ServiceContractResource($serviceContract->load('elevator')),
            message: 'Service contract created successfully.',
            status: 201,
        );
    }

    public function update(UpdateServiceContractRequest $request, ServiceContract $serviceContract): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('elevator_uuid', $data)) {
            $elevator = Elevator::where('uuid', $data['elevator_uuid'])->firstOrFail();
            unset($data['elevator_uuid']);
            $data['elevator_id'] = $elevator->id;
        }

        $serviceContract->update($data);

        return ApiResponse::success(
            data: new ServiceContractResource($serviceContract->fresh()->load('elevator')),
            message: 'Service contract updated successfully.',
        );
    }

    public function destroy(ServiceContract $serviceContract): JsonResponse
    {
        $serviceContract->delete();

        return ApiResponse::success(
            message: 'Service contract deleted successfully.',
        );
    }
}
