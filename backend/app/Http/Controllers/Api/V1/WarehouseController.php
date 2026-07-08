<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $warehouses = ListQuery::for(Warehouse::query()->with('user'), $request)
            ->filterable(['type', 'is_active'])
            ->searchable(['name'])
            ->sortable(['name', 'type', 'created_at', 'updated_at'])
            ->dateRange('created_at')
            ->paginate();

        return ApiResponse::paginated($warehouses, WarehouseResource::class);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return ApiResponse::success(data: new WarehouseResource($warehouse->load('user')));
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $data = $this->resolveUser($request->validated());
        $warehouse = Warehouse::create($data);

        return ApiResponse::success(
            data: new WarehouseResource($warehouse->load('user')),
            message: 'Warehouse created successfully.',
            status: 201,
        );
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($this->resolveUser($request->validated()));

        return ApiResponse::success(
            data: new WarehouseResource($warehouse->fresh()->load('user')),
            message: 'Warehouse updated successfully.',
        );
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return ApiResponse::success(message: 'Warehouse deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveUser(array $data): array
    {
        if (array_key_exists('user_uuid', $data)) {
            $data['user_id'] = $data['user_uuid'] === null ? null : User::where('uuid', $data['user_uuid'])->firstOrFail()->id;
            unset($data['user_uuid']);
        }

        return $data;
    }
}
