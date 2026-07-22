<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Company;
use App\Models\Elevator;
use App\Models\InspectionImport;
use App\Services\InspectionImport\FetchedMailMessage;
use App\Services\InspectionImport\MailFetcherInterface;
use App\Services\InspectionImport\PdfTextExtractorInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FetchInspectionMailCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<FetchedMailMessage> */
    private array $inbox = [];

    /** @var list<string> */
    private array $processedUids = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['inspection_import.imap.host' => 'imap.test']);

        $this->app->bind(PdfTextExtractorInterface::class, fn () => new class implements PdfTextExtractorInterface
        {
            public function extract(string $contents): string
            {
                return $contents;
            }
        });

        $test = $this;

        $this->app->instance(MailFetcherInterface::class, new class($test) implements MailFetcherInterface
        {
            public function __construct(private FetchInspectionMailCommandTest $test) {}

            public function fetchUnprocessed(): array
            {
                return $this->test->inbox();
            }

            public function markProcessed(FetchedMailMessage $message): void
            {
                $this->test->recordProcessed($message->uid);
            }
        });
    }

    /**
     * @return list<FetchedMailMessage>
     */
    public function inbox(): array
    {
        return $this->inbox;
    }

    public function recordProcessed(string $uid): void
    {
        $this->processedUids[] = $uid;
    }

    private function message(string $uid = '1'): FetchedMailMessage
    {
        return new FetchedMailMessage(
            uid: $uid,
            messageId: "<msg-{$uid}@royalcert.com>",
            from: 'rapor@royalcert.com',
            subject: 'Fatih Apartmanı Asansör Denetim Raporu',
            receivedAt: CarbonImmutable::parse('2026-07-02 09:00'),
            pdfAttachments: [[
                'filename' => 'rapor.pdf',
                'contents' => "Asansör Periyodik Kontrol Muayene Raporu\nRapor No: RC-1\nKontrol Tarihi: 01.07.2026\n(P)\nMAVİ EKSİKLER\n10 - 1.1.2 Aydınlatma armatürü yeterli şekilde aydınlatılmalıdır.\n",
            ]],
        );
    }

    public function test_command_ingests_processes_and_marks_mails(): void
    {
        $company = Company::factory()->create();
        $building = Building::factory()->create(['company_id' => $company->id, 'name' => 'Fatih Apartmanı']);
        Elevator::factory()->create(['building_id' => $building->id]);

        $this->inbox = [$this->message()];

        $this->artisan('inspections:fetch-mail')
            ->expectsOutputToContain('imported')
            ->assertSuccessful();

        $import = InspectionImport::withoutGlobalScopes()->first();
        $this->assertSame('imported', $import->status);
        $this->assertSame($company->id, $import->company_id);
        $this->assertSame(['1'], $this->processedUids);
    }

    public function test_rerunning_the_command_skips_duplicates(): void
    {
        Company::factory()->create();

        $this->inbox = [$this->message()];

        $this->artisan('inspections:fetch-mail')->assertSuccessful();
        $this->artisan('inspections:fetch-mail')->assertSuccessful();

        $this->assertSame(1, InspectionImport::withoutGlobalScopes()->count());
    }

    public function test_command_skips_quietly_when_imap_is_not_configured(): void
    {
        config(['inspection_import.imap.host' => null]);

        $this->artisan('inspections:fetch-mail')->assertSuccessful();

        $this->assertSame(0, InspectionImport::withoutGlobalScopes()->count());
    }

    public function test_dry_run_persists_nothing(): void
    {
        Company::factory()->create();
        $this->inbox = [$this->message()];

        $this->artisan('inspections:fetch-mail --dry-run')->assertSuccessful();

        $this->assertSame(0, InspectionImport::withoutGlobalScopes()->count());
        $this->assertSame([], $this->processedUids);
    }
}
