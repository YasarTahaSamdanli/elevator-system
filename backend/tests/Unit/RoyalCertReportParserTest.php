<?php

namespace Tests\Unit;

use App\Services\InspectionImport\RoyalCertReportParser;
use PHPUnit\Framework\TestCase;

/**
 * The label/type heuristics mirror the office's proven Python mail script;
 * these fixtures encode its decision table. Real sample PDFs go to
 * tests/Fixtures/royalcert/ once available.
 */
class RoyalCertReportParserTest extends TestCase
{
    private RoyalCertReportParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new RoyalCertReportParser;
    }

    private function reportText(string $body = ''): string
    {
        return "ROYALCERT Asansör Periyodik Kontrol Muayene Raporu\n".
            "Rapor No: RC-2026-0042\n".
            "Kontrol Tarihi: 01.07.2026\n".
            $body;
    }

    public function test_red_label_wins_over_other_sections(): void
    {
        $text = $this->reportText("(P)\nKIRMIZI EKSİKLER\nSARI EKSİKLER\nMAVİ EKSİKLER\n");

        $this->assertSame('red', $this->parser->parse($text, 'Test Apt')->label);
    }

    public function test_yellow_label_when_no_red_section(): void
    {
        $text = $this->reportText("(P)\nSARI EKSİKLER\nMAVİ EKSİKLER\n");

        $this->assertSame('yellow', $this->parser->parse($text, 'Test Apt')->label);
    }

    public function test_blue_label_when_only_blue_section(): void
    {
        $text = $this->reportText("(P)\nMAVİ EKSİKLER\n");

        $this->assertSame('blue', $this->parser->parse($text, 'Test Apt')->label);
    }

    public function test_green_label_when_no_defect_sections(): void
    {
        $this->assertSame('green', $this->parser->parse($this->reportText('(P)'), 'Test Apt')->label);
    }

    public function test_ascii_variant_of_section_names_is_recognized(): void
    {
        // Depending on how the PDF library decodes the text layer, Turkish
        // characters may come out as ASCII (the Python script hit both).
        $text = $this->reportText("(P)\nKIRMIZI EKSIKLER\n");

        $this->assertSame('red', $this->parser->parse($text, 'Test Apt')->label);
    }

    public function test_type_is_follow_up_when_both_markers_present(): void
    {
        $text = $this->reportText("(P) (T)\n");

        $this->assertSame('follow_up', $this->parser->parse($text, 'Test Apt')->type);
    }

    public function test_type_is_periodic_with_only_p_marker(): void
    {
        $this->assertSame('periodic', $this->parser->parse($this->reportText('(P)'), 'Test Apt')->type);
    }

    public function test_identity_comes_from_mail_subject_with_boilerplate_stripped(): void
    {
        $report = $this->parser->parse($this->reportText(), 'Fatih Apartmanı Asansör Denetim Raporu');

        $this->assertSame('Fatih Apartmanı', $report->identity);
        $this->assertSame('fatih apartmani', $report->identityNormalized);
    }

    public function test_report_number_and_dates_are_extracted(): void
    {
        $report = $this->parser->parse($this->reportText(), 'Test Apt');

        $this->assertSame('RC-2026-0042', $report->reportNumber);
        $this->assertSame('2026-07-01', $report->inspectedAt);
    }

    public function test_non_report_document_is_incomplete(): void
    {
        $report = $this->parser->parse("Sayın yetkili,\nfatura ektedir.\n", 'Fatura');

        $this->assertFalse($report->isComplete());
        $this->assertNull($report->label);
    }

    public function test_normalize_identity_folds_turkish_characters_and_punctuation(): void
    {
        $this->assertSame(
            'isgoren is merkezi b blok',
            RoyalCertReportParser::normalizeIdentity('İŞGÖREN İş Merkezi (B-Blok)'),
        );
    }
}
