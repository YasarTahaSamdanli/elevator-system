<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\InspectionImport\InspectionImportService;
use App\Services\InspectionImport\MailFetcherInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchInspectionMail extends Command
{
    /**
     * @var string
     */
    protected $signature = 'inspections:fetch-mail {--dry-run : List what would be ingested without saving anything}';

    /**
     * @var string
     */
    protected $description = 'Poll the report mailbox for inspection report PDFs (RoyalCert) and run them through the import pipeline (idempotent — duplicates are skipped)';

    public function handle(MailFetcherInterface $fetcher, InspectionImportService $service): int
    {
        if (! config('inspection_import.imap.host')) {
            $this->warn('IMAP_HOST is not configured; skipping mail fetch.');

            return self::SUCCESS;
        }

        $companyId = $this->resolveCompanyId();

        if ($companyId === null) {
            return self::FAILURE;
        }

        $messages = $fetcher->fetchUnprocessed();

        if ($messages === []) {
            $this->info('No new report mails.');

            return self::SUCCESS;
        }

        $counters = ['ingested' => 0, 'duplicate' => 0, 'imported' => 0, 'needs_review' => 0, 'failed' => 0];

        foreach ($messages as $message) {
            $allPersisted = true;

            foreach (array_keys($message->pdfAttachments) as $index) {
                $mail = $message->toIncomingReportMail($index);

                if ($this->option('dry-run')) {
                    $this->line("[dry-run] {$mail->subject} — {$mail->filename}");

                    continue;
                }

                try {
                    $import = $service->ingestEmail($mail, $companyId);

                    if ($import === null) {
                        $counters['duplicate']++;

                        continue;
                    }

                    $counters['ingested']++;
                    $import = $service->process($import);
                    $counters[$import->status] = ($counters[$import->status] ?? 0) + 1;

                    $this->line("{$mail->filename}: {$import->status}".($import->review_reason ? " ({$import->review_reason})" : ''));
                } catch (Throwable $e) {
                    $allPersisted = false;
                    $counters['failed']++;

                    Log::error('Inspection mail ingest failed.', [
                        'subject' => $mail->subject,
                        'filename' => $mail->filename,
                        'exception' => $e,
                    ]);

                    $this->error("{$mail->filename}: {$e->getMessage()}");
                }
            }

            // Only mark the mail handled once every attachment is persisted;
            // a retried message is harmless thanks to the checksum dedupe.
            if ($allPersisted && ! $this->option('dry-run')) {
                $fetcher->markProcessed($message);
            }
        }

        $this->info(sprintf(
            'Done. Ingested %d (imported %d, needs review %d, failed %d), skipped %d duplicate(s).',
            $counters['ingested'],
            $counters['imported'],
            $counters['needs_review'],
            $counters['failed'],
            $counters['duplicate'],
        ));

        return $counters['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveCompanyId(): ?int
    {
        $configured = config('inspection_import.company_id');

        if ($configured) {
            $company = Company::find($configured);

            if ($company === null) {
                $this->error("INSPECTION_IMPORT_COMPANY_ID={$configured} does not match any company.");

                return null;
            }

            return $company->id;
        }

        // Single-company deployment convenience: when exactly one company
        // exists, no configuration is needed.
        $companies = Company::query()->limit(2)->pluck('id');

        if ($companies->count() === 1) {
            return $companies->first();
        }

        $this->error('Set INSPECTION_IMPORT_COMPANY_ID in .env (multiple companies exist).');

        return null;
    }
}
