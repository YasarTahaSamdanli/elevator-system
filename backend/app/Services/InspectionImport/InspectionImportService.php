<?php

namespace App\Services\InspectionImport;

use App\Models\Elevator;
use App\Models\ElevatorInspection;
use App\Models\InspectionImport;
use App\Models\InspectionSourceElevator;
use App\Models\PrintJob;
use App\Models\Scopes\CompanyScope;
use App\Models\User;
use App\Services\InspectionWorkOrderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Orchestrates the RoyalCert report pipeline: store PDF → extract text →
 * parse → match elevator → create inspection → auto work order → print job.
 * Every failure path parks the import in the review queue with a reason;
 * the PDF is always stored first, regardless of outcome.
 *
 * Runs in console context too (no Auth, CompanyScope inactive): company_id
 * is always assigned explicitly, never derived from Auth.
 */
class InspectionImportService
{
    public function __construct(
        private readonly PdfTextExtractorInterface $extractor,
        private readonly RoyalCertReportParser $parser,
        private readonly ElevatorMatcher $matcher,
        private readonly InspectionWorkOrderService $workOrderService,
    ) {}

    public function ingestUpload(UploadedFile $file, int $companyId, ?int $userId): InspectionImport
    {
        return $this->ingest($file->getContent(), $companyId, [
            'source' => 'upload',
            'original_filename' => $file->getClientOriginalName(),
            'created_by' => $userId,
        ]) ?? $this->findDuplicate(hash('sha256', $file->getContent()), $companyId);
    }

    /**
     * Returns null when the exact same PDF was already ingested (safe to
     * re-run the mail fetch at any time).
     */
    public function ingestEmail(IncomingReportMail $mail, int $companyId): ?InspectionImport
    {
        return $this->ingest($mail->pdfContents, $companyId, [
            'source' => 'email',
            'message_id' => $mail->messageId,
            'mail_from' => $mail->from,
            'mail_subject' => $mail->subject,
            'mail_received_at' => $mail->receivedAt,
            'original_filename' => $mail->filename,
        ]);
    }

    public function process(InspectionImport $import): InspectionImport
    {
        if (! in_array($import->status, ['pending', 'needs_review', 'failed'], true)) {
            return $import;
        }

        try {
            $text = $this->extractor->extract(
                Storage::disk($import->pdf_disk)->get($import->pdf_path),
            );

            if (trim($text) === '') {
                $import->markNeedsReview(InspectionImport::REVIEW_NO_TEXT_LAYER, 'The PDF has no extractable text layer.');

                return $import;
            }

            $report = $this->parser->parse($text, $import->mail_subject ?? $this->identityFromFilename($import));

            $import->forceFill([
                'parsed_payload' => $report->toArray(),
                'report_number' => $report->reportNumber,
            ])->save();

            if (! $report->isComplete()) {
                $import->markNeedsReview(
                    InspectionImport::REVIEW_PARSE_FAILED,
                    implode(' ', $report->warnings) ?: 'Required report fields could not be extracted.',
                );

                return $import;
            }

            $match = $this->matcher->match($import->company_id, $report);

            if (! $match->isMatched()) {
                $import->markNeedsReview($match->failureReason);

                return $import;
            }

            $this->finalize($import, $match->elevator, $match->via, $report);
        } catch (PdfExtractionException $e) {
            $import->markNeedsReview(InspectionImport::REVIEW_PARSE_FAILED, $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Inspection import processing failed.', [
                'inspection_import' => $import->uuid,
                'exception' => $e,
            ]);

            $import->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();
        }

        return $import;
    }

    /**
     * Operator resolved a review-queue import to an elevator: learn the
     * mapping so the same report identity auto-matches from now on, then
     * run the tail of the pipeline.
     */
    public function matchManually(InspectionImport $import, Elevator $elevator, ?User $actor = null): InspectionImport
    {
        $report = $this->reportFromPayload($import);

        if ($report->label === null) {
            throw ValidationException::withMessages([
                'elevator_uuid' => ['The report has no parsed label; record the inspection manually instead.'],
            ]);
        }

        if ($report->identityNormalized !== null) {
            $this->learnMapping($import, $elevator, $report->identityNormalized);
        }

        $import->created_by ??= $actor?->id;

        $this->finalize($import, $elevator, 'manual', $report);

        return $import;
    }

    public function ignore(InspectionImport $import): InspectionImport
    {
        $import->forceFill(['status' => 'ignored'])->save();

        return $import;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ingest(string $pdfContents, int $companyId, array $attributes): ?InspectionImport
    {
        $sha256 = hash('sha256', $pdfContents);

        if ($this->findDuplicate($sha256, $companyId) !== null) {
            return null;
        }

        $uuid = (string) Str::uuid();
        $disk = config('inspection_import.disk', 'local');
        $path = sprintf('inspection-imports/%d/%s/%s.pdf', $companyId, now()->format('Y/m'), $uuid);

        Storage::disk($disk)->put($path, $pdfContents);

        $import = new InspectionImport($attributes + [
            'status' => 'pending',
            'pdf_disk' => $disk,
            'pdf_path' => $path,
            'pdf_sha256' => $sha256,
        ]);
        $import->uuid = $uuid;
        $import->company_id = $companyId;
        $import->save();

        return $import;
    }

    private function findDuplicate(string $sha256, int $companyId): ?InspectionImport
    {
        return InspectionImport::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('pdf_sha256', $sha256)
            ->first();
    }

    private function finalize(InspectionImport $import, Elevator $elevator, string $via, ParsedReport $report): void
    {
        if ($this->isDuplicateReport($import, $elevator, $report)) {
            $import->forceFill(['elevator_id' => $elevator->id, 'matched_via' => $via])->save();
            $import->markNeedsReview(
                InspectionImport::REVIEW_DUPLICATE_REPORT,
                "An inspection with report number {$report->reportNumber} already exists for this elevator.",
            );

            return;
        }

        $inspection = DB::transaction(function () use ($import, $elevator, $via, $report): ElevatorInspection {
            $inspection = new ElevatorInspection([
                'elevator_id' => $elevator->id,
                'type' => $report->type,
                'inspection_body' => $report->inspectionBody,
                'inspected_at' => $report->inspectedAt
                    ?? $import->mail_received_at?->toDateString()
                    ?? now()->toDateString(),
                'label' => $report->label,
                'report_number' => $report->reportNumber,
                'next_inspection_date' => $report->nextInspectionDate,
                'created_by' => $import->created_by,
                'notes' => 'RoyalCert raporundan otomatik içe aktarıldı.',
            ]);
            $inspection->company_id = $elevator->company_id;
            $inspection->save();

            foreach ($report->findings as $description) {
                $finding = $inspection->findings()->make(['description' => $description]);
                $finding->company_id = $inspection->company_id;
                $finding->save();
            }

            $import->forceFill([
                'status' => 'imported',
                'review_reason' => null,
                'error_message' => null,
                'elevator_id' => $elevator->id,
                'elevator_inspection_id' => $inspection->id,
                'matched_via' => $via,
            ])->save();

            return $inspection;
        });

        // Outside the transaction: the import stands on its own even when
        // the work order guards reject (no active contract, ...).
        if (in_array($report->label, config('inspection_import.auto_work_order_labels', []), true)) {
            try {
                $this->workOrderService->createFor($inspection);
            } catch (ValidationException $e) {
                $import->forceFill([
                    'work_order_error' => collect($e->errors())->flatten()->implode(' '),
                ])->save();
            }
        }

        if (config('inspection_import.auto_print')) {
            $printJob = new PrintJob(['inspection_import_id' => $import->id]);
            $printJob->company_id = $import->company_id;
            $printJob->save();
        }
    }

    private function isDuplicateReport(InspectionImport $import, Elevator $elevator, ParsedReport $report): bool
    {
        if ($report->reportNumber === null) {
            return false;
        }

        return ElevatorInspection::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $import->company_id)
            ->where('elevator_id', $elevator->id)
            ->where('report_number', $report->reportNumber)
            ->exists();
    }

    private function learnMapping(InspectionImport $import, Elevator $elevator, string $externalKey): void
    {
        $mapping = InspectionSourceElevator::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $import->company_id)
            ->where('source', 'royalcert')
            ->where('external_key', $externalKey)
            ->first();

        if ($mapping === null) {
            $mapping = new InspectionSourceElevator([
                'source' => 'royalcert',
                'external_key' => $externalKey,
            ]);
            $mapping->company_id = $import->company_id;
        }

        $mapping->elevator_id = $elevator->id;
        $mapping->save();
    }

    private function reportFromPayload(InspectionImport $import): ParsedReport
    {
        $payload = $import->parsed_payload ?? [];

        return new ParsedReport(
            reportNumber: $payload['report_number'] ?? null,
            inspectedAt: $payload['inspected_at'] ?? null,
            label: $payload['label'] ?? null,
            type: $payload['type'] ?? 'periodic',
            inspectionBody: $payload['inspection_body'] ?? 'RoyalCert',
            nextInspectionDate: $payload['next_inspection_date'] ?? null,
            identity: $payload['identity'] ?? null,
            identityNormalized: $payload['identity_normalized'] ?? null,
            registrationNumber: $payload['registration_number'] ?? null,
            findings: $payload['findings'] ?? [],
            warnings: $payload['warnings'] ?? [],
        );
    }

    private function identityFromFilename(InspectionImport $import): ?string
    {
        if ($import->original_filename === null) {
            return null;
        }

        $name = pathinfo($import->original_filename, PATHINFO_FILENAME);

        return $name === '' ? null : $name;
    }
}
