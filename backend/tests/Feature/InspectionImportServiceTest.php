<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\ElevatorInspection;
use App\Models\InspectionImport;
use App\Models\PrintJob;
use App\Models\ServiceContract;
use App\Services\InspectionImport\IncomingReportMail;
use App\Services\InspectionImport\InspectionImportService;
use App\Services\InspectionImport\PdfTextExtractorInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspectionImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Building $building;

    private Elevator $elevator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // The fake "PDF bytes" are the report text itself; the extractor is
        // an identity function so tests control the parsed content directly.
        $this->app->bind(PdfTextExtractorInterface::class, fn () => new class implements PdfTextExtractorInterface
        {
            public function extract(string $contents): string
            {
                return $contents;
            }
        });

        $this->company = Company::factory()->create();
        $this->building = Building::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Fatih Apartmanı',
        ]);
        $this->elevator = Elevator::factory()->create(['building_id' => $this->building->id]);
    }

    private function service(): InspectionImportService
    {
        return app(InspectionImportService::class);
    }

    private function reportText(string $body = "(P)\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.\n", string $reportNumber = 'RC-2026-0042'): string
    {
        return "ROYALCERT Asansör Periyodik Kontrol Muayene Raporu\n".
            "Rapor No: {$reportNumber}\n".
            "Kontrol Tarihi: 01.07.2026\n".
            $body;
    }

    private function mail(?string $contents = null, string $subject = 'Fatih Apartmanı Asansör Denetim Raporu'): IncomingReportMail
    {
        return new IncomingReportMail(
            messageId: '<'.uniqid().'@royalcert.com>',
            from: 'rapor@royalcert.com',
            subject: $subject,
            receivedAt: CarbonImmutable::parse('2026-07-02 09:00'),
            filename: 'rapor.pdf',
            pdfContents: $contents ?? $this->reportText(),
        );
    }

    public function test_red_report_imports_creates_inspection_work_order_and_print_job(): void
    {
        ServiceContract::factory()->create(['elevator_id' => $this->elevator->id, 'status' => 'active']);

        $import = $this->service()->ingestEmail($this->mail(), $this->company->id);
        $import = $this->service()->process($import);

        $this->assertSame('imported', $import->status);
        $this->assertSame('building_name', $import->matched_via);
        $this->assertNull($import->work_order_error);
        Storage::disk('local')->assertExists($import->pdf_path);

        $inspection = $import->inspection()->withoutGlobalScopes()->first();
        $this->assertSame('red', $inspection->label);
        $this->assertSame('RC-2026-0042', $inspection->report_number);
        $this->assertSame('2026-07-01', $inspection->inspected_at->toDateString());
        // Red label: +60 days follow-up window auto-suggested (EK 7).
        $this->assertSame('2026-08-30', $inspection->follow_up_due_date->toDateString());
        $this->assertSame($this->company->id, $inspection->company_id);

        // The finding line is stored with its report structure.
        $finding = $inspection->findings()->withoutGlobalScopes()->first();
        $this->assertSame('Kat kapı kilit muhafazaları takılmalı.', $finding->description);
        $this->assertSame('red', $finding->severity);
        $this->assertSame('2.7.8', $finding->item_code);
        $this->assertSame(1, $finding->position);

        // Elevator label cache refreshed.
        $this->assertSame('red', $this->elevator->fresh()->current_label);

        // Auto work order for red label.
        $this->assertNotNull($inspection->work_order_id);
        $this->assertSame('critical', $inspection->workOrder()->withoutGlobalScopes()->first()->priority);
        $this->assertSame($this->company->id, $inspection->workOrder()->withoutGlobalScopes()->first()->company_id);

        // Print job queued for the office agent.
        $printJob = PrintJob::withoutGlobalScopes()->where('inspection_import_id', $import->id)->first();
        $this->assertNotNull($printJob);
        $this->assertSame('pending', $printJob->status);
        $this->assertSame($this->company->id, $printJob->company_id);
    }

    public function test_missing_active_contract_keeps_import_but_records_work_order_error(): void
    {
        $import = $this->service()->process(
            $this->service()->ingestEmail($this->mail(), $this->company->id),
        );

        $this->assertSame('imported', $import->status);
        $this->assertNotNull($import->work_order_error);
        $this->assertNotNull($import->elevator_inspection_id);
    }

    public function test_green_report_does_not_open_a_work_order(): void
    {
        ServiceContract::factory()->create(['elevator_id' => $this->elevator->id, 'status' => 'active']);

        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText("(P)\n")),
            $this->company->id,
        ));

        $this->assertSame('imported', $import->status);
        $this->assertNull($import->inspection()->withoutGlobalScopes()->first()->work_order_id);
    }

    public function test_same_pdf_is_ingested_only_once(): void
    {
        $mail = $this->mail();

        $this->assertNotNull($this->service()->ingestEmail($mail, $this->company->id));
        $this->assertNull($this->service()->ingestEmail($mail, $this->company->id));
        $this->assertSame(1, InspectionImport::withoutGlobalScopes()->count());
    }

    public function test_report_matches_via_elevator_registration_number_when_name_is_unknown(): void
    {
        $this->elevator->forceFill(['registration_number' => '146402649-1'])->save();

        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail(
                $this->reportText("(P)\n146402649-1\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.\n"),
                subject: 'Bilinmeyen Bina Asansör Denetim Raporu',
            ),
            $this->company->id,
        ));

        $this->assertSame('imported', $import->status);
        $this->assertSame('registration_number', $import->matched_via);
        $this->assertSame($this->elevator->id, $import->elevator_id);
    }

    public function test_matched_elevator_learns_the_reports_registration_number(): void
    {
        $this->elevator->forceFill(['registration_number' => null])->save();

        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText(
                "(P)\n146402649-1\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.\n",
            )),
            $this->company->id,
        ));

        $this->assertSame('imported', $import->status);
        $this->assertSame('building_name', $import->matched_via);
        // Backfilled from the report: the next report for this elevator
        // matches on the registration number even if the name changes.
        $this->assertSame('146402649-1', $this->elevator->fresh()->registration_number);
    }

    public function test_unmatched_building_lands_in_review_queue_with_pdf_kept(): void
    {
        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail(subject: 'Bilinmeyen Bina Asansör Denetim Raporu'),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_ELEVATOR_NOT_FOUND, $import->review_reason);
        $this->assertNotNull($import->parsed_payload);
        Storage::disk('local')->assertExists($import->pdf_path);
    }

    public function test_building_with_multiple_elevators_needs_review(): void
    {
        Elevator::factory()->create(['building_id' => $this->building->id]);

        $import = $this->service()->process(
            $this->service()->ingestEmail($this->mail(), $this->company->id),
        );

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_MULTIPLE_MATCHES, $import->review_reason);
    }

    public function test_non_report_pdf_needs_review_as_parse_failed(): void
    {
        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail("Sayın yetkili,\nfatura ektedir.\n"),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_PARSE_FAILED, $import->review_reason);
    }

    public function test_defect_report_without_extractable_findings_needs_review(): void
    {
        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText("(P)\nKIRMIZI EKSİKLER\n")),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_PARSE_FAILED, $import->review_reason);
    }

    public function test_declared_finding_count_mismatch_needs_review(): void
    {
        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText(
                "(P)\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.\n".
                "3 Adet Uygunsuzluk Tespit Edilmiştir.\n",
            )),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_PARSE_FAILED, $import->review_reason);
        $this->assertStringContainsString('declares 3 findings', (string) $import->error_message);
    }

    public function test_pdf_without_text_layer_needs_review(): void
    {
        $import = $this->service()->process($this->service()->ingestEmail(
            $this->mail('   '),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $import->status);
        $this->assertSame(InspectionImport::REVIEW_NO_TEXT_LAYER, $import->review_reason);
    }

    public function test_manual_match_learns_mapping_so_next_report_auto_matches(): void
    {
        Elevator::factory()->create(['building_id' => $this->building->id]); // forces multiple_matches

        $import = $this->service()->process(
            $this->service()->ingestEmail($this->mail(), $this->company->id),
        );
        $this->assertSame('needs_review', $import->status);

        $import = $this->service()->matchManually($import, $this->elevator);

        $this->assertSame('imported', $import->status);
        $this->assertSame('manual', $import->matched_via);

        // A later report with the same subject now resolves via the mapping.
        $second = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText(reportNumber: 'RC-2026-0099')),
            $this->company->id,
        ));

        $this->assertSame('imported', $second->status);
        $this->assertSame('mapping', $second->matched_via);
        $this->assertSame($this->elevator->id, $second->elevator_id);
    }

    public function test_duplicate_report_number_for_same_elevator_needs_review(): void
    {
        $this->service()->process($this->service()->ingestEmail($this->mail(), $this->company->id));

        // Same report number arrives again in a byte-different PDF.
        $second = $this->service()->process($this->service()->ingestEmail(
            $this->mail($this->reportText("(P)\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı. Ek açıklama\n")),
            $this->company->id,
        ));

        $this->assertSame('needs_review', $second->status);
        $this->assertSame(InspectionImport::REVIEW_DUPLICATE_REPORT, $second->review_reason);
    }

    public function test_late_arriving_older_report_does_not_overwrite_newer_label_cache(): void
    {
        ElevatorInspection::factory()->create([
            'elevator_id' => $this->elevator->id,
            'inspected_at' => '2026-07-10',
            'label' => 'green',
        ]);
        $this->assertSame('green', $this->elevator->fresh()->current_label);

        // Imported report is dated 2026-07-01 — older than the existing one.
        $import = $this->service()->process(
            $this->service()->ingestEmail($this->mail(), $this->company->id),
        );

        $this->assertSame('imported', $import->status);
        $this->assertSame('green', $this->elevator->fresh()->current_label);
    }

    public function test_reports_never_cross_companies(): void
    {
        $otherCompany = Company::factory()->create();
        Building::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Fatih Apartmanı', // same name, different company
        ]);

        $import = $this->service()->process(
            $this->service()->ingestEmail($this->mail(), $otherCompany->id),
        );

        // Matches the *other* company's building; that building has no elevator.
        $this->assertSame('needs_review', $import->status);
        $this->assertSame($otherCompany->id, $import->company_id);
    }
}
