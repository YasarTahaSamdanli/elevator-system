<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrintJob\StorePrintJobRequest;
use App\Http\Requests\PrintJob\UpdatePrintJobRequest;
use App\Http\Resources\PrintJobResource;
use App\Models\InspectionImport;
use App\Models\PrintJob;
use App\Support\ApiResponse;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = ListQuery::for(PrintJob::query()->with('inspectionImport'), $request)
            ->filterable([
                'purpose',
                // "pending" also surfaces stale "printing" claims (agent died
                // mid-print) so the office agent picks them up again.
                'status' => function (Builder $query, mixed $value): Builder {
                    if ($value !== 'pending') {
                        return $query->where('status', $value);
                    }

                    return $query->where(fn (Builder $q) => $q
                        ->where('status', 'pending')
                        ->orWhere(fn (Builder $stale) => $stale
                            ->where('status', 'printing')
                            ->where('claimed_at', '<=', now()->subMinutes(PrintJob::STALE_CLAIM_MINUTES)),
                        ));
                },
            ])
            ->sortable(['status', 'created_at', 'printed_at', 'updated_at'], 'created_at')
            ->dateRange('created_at', 'printed_at')
            ->paginate();

        return ApiResponse::paginated($jobs, PrintJobResource::class);
    }

    public function show(PrintJob $printJob): JsonResponse
    {
        return ApiResponse::success(
            data: new PrintJobResource($printJob->load('inspectionImport')),
        );
    }

    /**
     * Manual reprint of a stored report PDF.
     */
    public function store(StorePrintJobRequest $request): JsonResponse
    {
        $import = InspectionImport::where('uuid', $request->validated('inspection_import_uuid'))->firstOrFail();

        $job = new PrintJob(['inspection_import_id' => $import->id]);
        $job->company_id = $import->company_id;
        $job->save();

        return ApiResponse::success(
            data: new PrintJobResource($job->load('inspectionImport')),
            message: 'Print job queued.',
            status: 201,
        );
    }

    /**
     * Status transitions driven by the office print agent: claim
     * (pending→printing), then done or failed.
     */
    public function update(UpdatePrintJobRequest $request, PrintJob $printJob): JsonResponse
    {
        $status = $request->validated('status');

        if (! $printJob->canTransitionTo($status)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition print job from '{$printJob->status}' to '{$status}'."],
            ]);
        }

        $printJob->status = $status;

        if ($status === 'printing') {
            $printJob->claimed_at = now();
            $printJob->attempts = $printJob->attempts + 1;
        }

        if ($status === 'done') {
            $printJob->printed_at = now();
        }

        if ($status === 'failed') {
            $printJob->error_message = $request->validated('error_message');
        }

        $printJob->save();

        return ApiResponse::success(
            data: new PrintJobResource($printJob->load('inspectionImport')),
            message: 'Print job updated.',
        );
    }

    /**
     * The file the agent should put on paper (the stored report PDF).
     */
    public function downloadFile(PrintJob $printJob): StreamedResponse
    {
        $import = $printJob->inspectionImport()->firstOrFail();

        return Storage::disk($import->pdf_disk)->response(
            $import->pdf_path,
            $import->original_filename ?? "{$import->uuid}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
