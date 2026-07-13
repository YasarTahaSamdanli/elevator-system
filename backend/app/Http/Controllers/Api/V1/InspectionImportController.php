<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InspectionImport\MatchInspectionImportRequest;
use App\Http\Requests\InspectionImport\StoreInspectionImportRequest;
use App\Http\Resources\InspectionImportResource;
use App\Models\Elevator;
use App\Models\InspectionImport;
use App\Services\InspectionImport\InspectionImportService;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionImportController extends Controller
{
    public function __construct(private readonly InspectionImportService $service) {}

    public function index(Request $request): JsonResponse
    {
        $imports = ListQuery::for(InspectionImport::query()->with(['elevator.building', 'inspection.workOrder']), $request)
            ->filterable([
                'status',
                'review_reason',
                'source',
                'matched_via',
                'elevator_uuid' => fn (Builder $query, mixed $value) => $query->whereHas(
                    'elevator',
                    fn (Builder $elevator) => $elevator->where('uuid', $value),
                ),
            ])
            ->searchable([
                'mail_subject',
                'mail_from',
                'original_filename',
                'report_number',
            ])
            ->sortable(['mail_received_at', 'status', 'created_at', 'updated_at'], '-created_at')
            ->dateRange('mail_received_at', 'created_at')
            ->paginate();

        return ApiResponse::paginated($imports, InspectionImportResource::class);
    }

    public function show(InspectionImport $inspectionImport): JsonResponse
    {
        return ApiResponse::success(
            data: new InspectionImportResource($inspectionImport->load(['elevator.building', 'inspection.workOrder'])),
        );
    }

    /**
     * Manual PDF upload — same pipeline as the mail intake.
     */
    public function store(StoreInspectionImportRequest $request): JsonResponse
    {
        $import = $this->service->ingestUpload(
            $request->file('file'),
            Auth::user()->company_id,
            Auth::id(),
        );

        $alreadyExisted = ! $import->wasRecentlyCreated;

        if (! $alreadyExisted) {
            $import = $this->service->process($import);
        }

        return ApiResponse::success(
            data: new InspectionImportResource($import->load(['elevator.building', 'inspection.workOrder'])),
            message: $alreadyExisted
                ? 'This PDF was already imported earlier.'
                : 'Report uploaded and processed.',
            status: $alreadyExisted ? 200 : 201,
        );
    }

    public function destroy(InspectionImport $inspectionImport): JsonResponse
    {
        $inspectionImport->delete();

        return ApiResponse::success(
            message: 'Inspection import deleted successfully.',
        );
    }

    public function downloadPdf(InspectionImport $inspectionImport): StreamedResponse
    {
        return Storage::disk($inspectionImport->pdf_disk)->response(
            $inspectionImport->pdf_path,
            $inspectionImport->original_filename ?? "{$inspectionImport->uuid}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function match(MatchInspectionImportRequest $request, InspectionImport $inspectionImport): JsonResponse
    {
        if ($inspectionImport->status !== 'needs_review') {
            throw ValidationException::withMessages([
                'status' => ['Only imports awaiting review can be matched manually.'],
            ]);
        }

        $elevator = Elevator::where('uuid', $request->validated('elevator_uuid'))->firstOrFail();

        $import = $this->service->matchManually($inspectionImport, $elevator, Auth::user());

        return ApiResponse::success(
            data: new InspectionImportResource($import->load(['elevator.building', 'inspection.workOrder'])),
            message: 'Report matched and imported.',
        );
    }

    public function retry(InspectionImport $inspectionImport): JsonResponse
    {
        if (! in_array($inspectionImport->status, ['needs_review', 'failed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only failed or review-queue imports can be retried.'],
            ]);
        }

        $import = $this->service->process($inspectionImport);

        return ApiResponse::success(
            data: new InspectionImportResource($import->load(['elevator.building', 'inspection.workOrder'])),
            message: 'Import re-processed.',
        );
    }

    public function ignore(InspectionImport $inspectionImport): JsonResponse
    {
        if ($inspectionImport->status === 'imported') {
            throw ValidationException::withMessages([
                'status' => ['An already imported report cannot be ignored.'],
            ]);
        }

        $import = $this->service->ignore($inspectionImport);

        return ApiResponse::success(
            data: new InspectionImportResource($import),
            message: 'Import ignored.',
        );
    }
}
