<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\InspectionImport;
use App\Models\User;
use App\Services\InspectionImport\PdfTextExtractorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InspectionImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->app->bind(PdfTextExtractorInterface::class, fn () => new class implements PdfTextExtractorInterface
        {
            public function extract(string $contents): string
            {
                return $contents;
            }
        });

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    public function test_unauthenticated_user_cannot_list_imports(): void
    {
        $this->getJson('/api/v1/inspection-imports')->assertUnauthorized();
    }

    public function test_upload_runs_the_import_pipeline(): void
    {
        $building = Building::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Fatih Apartmanı',
        ]);
        Elevator::factory()->create(['building_id' => $building->id]);

        $pdf = UploadedFile::fake()->createWithContent(
            'Fatih Apartmanı Asansör Denetim Raporu.pdf',
            "%PDF-1.4 Asansör Periyodik Kontrol Raporu\nRapor No: RC-1\nKontrol Tarihi: 01.07.2026\n(P)\nSARI EKSİKLER\n4 - 1.3.1 Makina dairesinde talimat bulunmalıdır.\n",
        );

        $response = $this->actingAs($this->user)->post('/api/v1/inspection-imports', ['file' => $pdf]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.parsed_payload.label', 'yellow');
    }

    public function test_upload_rejects_non_pdf_files(): void
    {
        $this->actingAs($this->user)
            ->post('/api/v1/inspection-imports', ['file' => UploadedFile::fake()->create('rapor.docx', 10, 'application/msword')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_list_can_filter_by_status(): void
    {
        InspectionImport::factory()->count(2)->needsReview()->create(['company_id' => $this->company->id]);
        InspectionImport::factory()->create(['company_id' => $this->company->id, 'status' => 'imported']);

        $this->actingAs($this->user)
            ->getJson('/api/v1/inspection-imports?filter[status]=needs_review')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_unknown_filter_field_returns_422(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/inspection-imports?filter[bogus]=1')
            ->assertUnprocessable();
    }

    public function test_other_companys_import_is_not_visible(): void
    {
        $foreign = InspectionImport::factory()->create();

        $this->actingAs($this->user)
            ->getJson("/api/v1/inspection-imports/{$foreign->uuid}")
            ->assertNotFound();
    }

    public function test_pdf_download_streams_the_stored_file(): void
    {
        Storage::disk('local')->put('inspection-imports/test.pdf', '%PDF-1.4 test');

        $import = InspectionImport::factory()->create([
            'company_id' => $this->company->id,
            'pdf_path' => 'inspection-imports/test.pdf',
        ]);

        $this->actingAs($this->user)
            ->get("/api/v1/inspection-imports/{$import->uuid}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_match_requires_review_status(): void
    {
        $building = Building::factory()->create(['company_id' => $this->company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);
        $import = InspectionImport::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'imported',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/inspection-imports/{$import->uuid}/match", ['elevator_uuid' => $elevator->uuid])
            ->assertUnprocessable();
    }

    public function test_match_resolves_a_review_queue_import(): void
    {
        $building = Building::factory()->create(['company_id' => $this->company->id]);
        $elevator = Elevator::factory()->create(['building_id' => $building->id]);

        Storage::disk('local')->put('inspection-imports/manual.pdf', 'x');
        $import = InspectionImport::factory()->needsReview()->create([
            'company_id' => $this->company->id,
            'pdf_path' => 'inspection-imports/manual.pdf',
            'parsed_payload' => [
                'label' => 'yellow',
                'type' => 'periodic',
                'inspection_body' => 'RoyalCert',
                'inspected_at' => '2026-07-01',
                'identity' => 'Bilinmeyen Bina',
                'identity_normalized' => 'bilinmeyen bina',
                'findings' => ['Kat kapısı kilidi arızalı'],
            ],
            'report_number' => 'RC-77',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/inspection-imports/{$import->uuid}/match", ['elevator_uuid' => $elevator->uuid])
            ->assertOk()
            ->assertJsonPath('data.status', 'imported')
            ->assertJsonPath('data.matched_via', 'manual');

        $this->assertSame('yellow', $elevator->fresh()->current_label);
        $this->assertDatabaseHas('inspection_source_elevators', [
            'company_id' => $this->company->id,
            'external_key' => 'bilinmeyen bina',
            'elevator_id' => $elevator->id,
        ]);
    }

    public function test_match_rejects_other_companys_elevator(): void
    {
        $foreignElevator = Elevator::factory()->create();
        $import = InspectionImport::factory()->needsReview()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/inspection-imports/{$import->uuid}/match", ['elevator_uuid' => $foreignElevator->uuid])
            ->assertUnprocessable();
    }

    public function test_retry_only_allowed_from_review_or_failed(): void
    {
        $import = InspectionImport::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'imported',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/inspection-imports/{$import->uuid}/retry")
            ->assertUnprocessable();
    }

    public function test_ignore_parks_an_import(): void
    {
        $import = InspectionImport::factory()->needsReview()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/inspection-imports/{$import->uuid}/ignore")
            ->assertOk()
            ->assertJsonPath('data.status', 'ignored');
    }
}
