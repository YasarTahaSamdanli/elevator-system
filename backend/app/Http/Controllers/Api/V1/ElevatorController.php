<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Elevator\StoreElevatorRequest;
use App\Http\Requests\Elevator\UpdateElevatorRequest;
use App\Http\Resources\ElevatorResource;
use App\Models\Building;
use App\Models\Elevator;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ElevatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $elevators = ListQuery::for(Elevator::query()->with('building'), $request)
            ->filterable([
                'status',
                'manufacturer',
                'current_label',
                // Exact match for the field QR flow: scan → resolve elevator.
                'qr_identifier',
                'building_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'building',
                    fn (Builder $building) => $building->where('uuid', $value),
                ),
            ])
            ->searchable([
                'serial_number',
                'qr_identifier',
                'name',
                'manufacturer',
                'model',
                'registration_number',
                'notes',
                'building' => ['name', 'code', 'address', 'city', 'district', 'manager_name', 'manager_phone'],
            ])
            ->sortable(['serial_number', 'name', 'manufacturer', 'status', 'installation_year', 'last_inspection_at', 'next_inspection_due', 'follow_up_due', 'created_at', 'updated_at'])
            ->dateRange('next_inspection_due', 'follow_up_due', 'created_at')
            ->paginate();

        return ApiResponse::paginated($elevators, ElevatorResource::class);
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
