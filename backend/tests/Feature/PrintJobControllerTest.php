<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InspectionImport;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintJobControllerTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
    }

    private function makeJob(array $state = []): PrintJob
    {
        $import = InspectionImport::factory()->create(['company_id' => $this->company->id]);

        return PrintJob::factory()->create(array_merge([
            'inspection_import_id' => $import->id,
            'company_id' => $this->company->id,
        ], $state));
    }

    public function test_agent_lists_pending_jobs(): void
    {
        $this->makeJob();
        $this->makeJob(['status' => 'done']);

        $this->actingAs($this->user)
            ->getJson('/api/v1/print-jobs?filter[status]=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pending_filter_includes_stale_printing_claims(): void
    {
        $this->makeJob(['status' => 'printing', 'claimed_at' => now()->subMinutes(30)]);
        $this->makeJob(['status' => 'printing', 'claimed_at' => now()->subMinutes(2)]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/print-jobs?filter[status]=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_claiming_a_job_sets_claim_metadata(): void
    {
        $job = $this->makeJob();

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$job->uuid}", ['status' => 'printing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'printing')
            ->assertJsonPath('data.attempts', 1);

        $this->assertNotNull($job->fresh()->claimed_at);
    }

    public function test_finished_jobs_cannot_move_again(): void
    {
        $job = $this->makeJob(['status' => 'done']);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$job->uuid}", ['status' => 'printing'])
            ->assertUnprocessable();
    }

    public function test_fresh_printing_claim_cannot_be_stolen(): void
    {
        $job = $this->makeJob(['status' => 'printing', 'claimed_at' => now()->subMinutes(2)]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$job->uuid}", ['status' => 'printing'])
            ->assertUnprocessable();
    }

    public function test_stale_printing_claim_can_be_reclaimed(): void
    {
        $job = $this->makeJob(['status' => 'printing', 'claimed_at' => now()->subMinutes(30), 'attempts' => 1]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$job->uuid}", ['status' => 'printing'])
            ->assertOk()
            ->assertJsonPath('data.attempts', 2);
    }

    public function test_marking_done_sets_printed_at(): void
    {
        $job = $this->makeJob(['status' => 'printing', 'claimed_at' => now()]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$job->uuid}", ['status' => 'done'])
            ->assertOk();

        $this->assertNotNull($job->fresh()->printed_at);
    }

    public function test_file_download_streams_the_report_pdf(): void
    {
        Storage::disk('local')->put('inspection-imports/job.pdf', '%PDF-1.4 test');

        $import = InspectionImport::factory()->create([
            'company_id' => $this->company->id,
            'pdf_path' => 'inspection-imports/job.pdf',
        ]);
        $job = PrintJob::factory()->create([
            'inspection_import_id' => $import->id,
            'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->user)
            ->get("/api/v1/print-jobs/{$job->uuid}/file")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_manual_reprint_queues_a_new_job(): void
    {
        $import = InspectionImport::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/print-jobs', ['inspection_import_uuid' => $import->uuid])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_other_companys_jobs_are_not_visible(): void
    {
        $foreignJob = PrintJob::factory()->create();

        $this->actingAs($this->user)
            ->patchJson("/api/v1/print-jobs/{$foreignJob->uuid}", ['status' => 'printing'])
            ->assertNotFound();
    }
}
