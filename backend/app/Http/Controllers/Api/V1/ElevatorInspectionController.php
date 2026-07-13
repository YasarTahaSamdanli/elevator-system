<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ElevatorInspection\StoreElevatorInspectionRequest;
use App\Http\Requests\ElevatorInspection\UpdateElevatorInspectionRequest;
use App\Http\Resources\ElevatorInspectionResource;
use App\Models\Elevator;
use App\Models\ElevatorInspection;
use App\Services\InspectionWorkOrderService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ElevatorInspectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $inspections = ListQuery::for(ElevatorInspection::query()->with(['elevator.building', 'workOrder', 'findings']), $request)
            ->filterable([
                'label',
                'type',
                'elevator_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'elevator',
                    fn (Builder $elevator) => $elevator->where('uuid', $value),
                ),
                'building_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'elevator.building',
                    fn (Builder $building) => $building->where('uuid', $value),
                ),
            ])
            ->searchable([
                'report_number',
                'inspection_body',
                'notes',
                'elevator' => ['serial_number', 'qr_identifier', 'name', 'manufacturer', 'model', 'registration_number'],
                'elevator.building' => ['name', 'code', 'address', 'city', 'district', 'manager_name', 'manager_phone'],
                'workOrder' => ['work_order_number', 'description', 'notes'],
                'findings' => ['description'],
                'createdBy' => ['name', 'email', 'phone'],
            ])
            ->sortable(['inspected_at', 'label', 'type', 'follow_up_due_date', 'next_inspection_date', 'created_at', 'updated_at'], '-inspected_at')
            ->dateRange('inspected_at', 'follow_up_due_date', 'next_inspection_date')
            ->paginate();

        return ApiResponse::paginated($inspections, ElevatorInspectionResource::class);
    }

    public function show(ElevatorInspection $elevatorInspection): JsonResponse
    {
        return ApiResponse::success(
            data: new ElevatorInspectionResource($elevatorInspection->load(['elevator.building', 'workOrder', 'findings'])),
        );
    }

    public function store(StoreElevatorInspectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $findings = $data['findings'] ?? [];
        unset($data['findings']);

        $elevator = Elevator::where('uuid', $data['elevator_uuid'])->firstOrFail();
        unset($data['elevator_uuid']);
        $data['elevator_id'] = $elevator->id;
        $data['created_by'] = Auth::id();

        $inspection = DB::transaction(function () use ($data, $findings): ElevatorInspection {
            $inspection = ElevatorInspection::create($data);
            $this->syncFindings($inspection, $findings);

            return $inspection;
        });

        return ApiResponse::success(
            data: new ElevatorInspectionResource($inspection->load(['elevator.building', 'workOrder', 'findings'])),
            message: 'Elevator inspection created successfully.',
            status: 201,
        );
    }

    public function update(UpdateElevatorInspectionRequest $request, ElevatorInspection $elevatorInspection): JsonResponse
    {
        $data = $request->validated();
        $findings = array_key_exists('findings', $data) ? $data['findings'] : null;
        unset($data['findings']);

        DB::transaction(function () use ($elevatorInspection, $data, $findings): void {
            $elevatorInspection->update($data);

            if ($findings !== null) {
                $this->syncFindings($elevatorInspection, $findings);
            }
        });

        return ApiResponse::success(
            data: new ElevatorInspectionResource($elevatorInspection->fresh()->load(['elevator.building', 'workOrder', 'findings'])),
            message: 'Elevator inspection updated successfully.',
        );
    }

    public function destroy(ElevatorInspection $elevatorInspection): JsonResponse
    {
        $elevatorInspection->delete();

        return ApiResponse::success(
            message: 'Elevator inspection deleted successfully.',
        );
    }

    public function createWorkOrder(ElevatorInspection $elevatorInspection, InspectionWorkOrderService $service): JsonResponse
    {
        $service->createFor($elevatorInspection);

        return ApiResponse::success(
            data: new ElevatorInspectionResource($elevatorInspection->fresh()->load(['elevator.building', 'workOrder', 'findings'])),
            message: 'Work order created from inspection successfully.',
            status: 201,
        );
    }

    /**
     * Replace the inspection's findings with the given payload; `findings`
     * always carries the full desired set, not a delta.
     */
    private function syncFindings(ElevatorInspection $inspection, array $findings): void
    {
        $inspection->findings()->delete();

        foreach ($findings as $finding) {
            $inspection->findings()->create([
                'description' => $finding['description'],
                'is_resolved' => $finding['is_resolved'] ?? false,
            ]);
        }
    }
}
