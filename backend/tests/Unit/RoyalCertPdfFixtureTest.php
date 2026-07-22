<?php

namespace Tests\Unit;

use App\Services\InspectionImport\RoyalCertReportParser;
use App\Services\InspectionImport\SmalotPdfTextExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Runs the real PDF extractor + parser against an actual RoyalCert EK 7
 * report (text-based conversion of the ÖRNEK EVLER 3 GÜVENSİZ report).
 * The PDF covers the report's first two pages only, so it deliberately
 * declares 61 findings while containing 28. The mismatch stays visible as
 * a parser warning, but the available findings are still actionable.
 */
class RoyalCertPdfFixtureTest extends TestCase
{
    private function parseFixture(): \App\Services\InspectionImport\ParsedReport
    {
        $pdf = (string) file_get_contents(
            __DIR__.'/../Fixtures/royalcert/ornek-evler-3-guvensiz.pdf',
        );

        return (new RoyalCertReportParser)->parse(
            (new SmalotPdfTextExtractor)->extract($pdf),
            'ÖRNEK EVLER 3 Asansör Denetim Raporu',
        );
    }

    public function test_header_fields_are_extracted_from_the_real_pdf(): void
    {
        $report = $this->parseFixture();

        $this->assertSame('R.NEV.26.1900', $report->reportNumber);
        $this->assertSame('2026-06-12', $report->inspectedAt);
        $this->assertSame('2026-08-11', $report->nextInspectionDate);
        $this->assertSame('red', $report->label);
        $this->assertSame('periodic', $report->type);
        $this->assertSame('ornek evler 3', $report->identityNormalized);
        $this->assertSame('146402649-1', $report->registrationNumber);
    }

    public function test_findings_are_extracted_with_section_colours(): void
    {
        $report = $this->parseFixture();

        $this->assertCount(28, $report->findings);

        $severities = array_count_values(array_column($report->findings, 'severity'));
        $this->assertSame(['red' => 3, 'yellow' => 6, 'blue' => 19], $severities);

        // Positions follow the report's continuous numbering.
        $this->assertSame(range(1, 28), array_column($report->findings, 'position'));

        $first = $report->findings[0];
        $this->assertSame('2.7.8', $first['item_code']);
        $this->assertSame('Kat kapı kilit muhafazaları takılmalı.', $first['description']);
        $this->assertNull($first['measurement']);

        // Measurement pulled out; other parentheses stay in the text.
        $third = $report->findings[2];
        $this->assertSame('195', $third['measurement']);
        $this->assertStringContainsString('TS EN ISO 3864-1', $third['description']);
        $this->assertStringNotContainsString('ÖLÇ', $third['description']);

        // Last finding is not polluted by the page footer.
        $last = $report->findings[27];
        $this->assertSame('2.11.12', $last['item_code']);
        $this->assertSame('Kabin üstü paten boşlukları ayarlanmalıdır.', $last['description']);
    }

    public function test_partial_report_keeps_declared_count_mismatch_as_warning(): void
    {
        $report = $this->parseFixture();

        $this->assertSame(61, $report->declaredFindingCount);
        $this->assertNull($report->findingsProblem());
        $this->assertContains(
            'Report declares 61 findings but 28 were extracted.',
            $report->warnings,
        );
    }
}
