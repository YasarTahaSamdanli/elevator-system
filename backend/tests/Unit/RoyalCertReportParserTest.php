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

    /**
     * Text laid out like the real EK 7 form (MS.FR.23): unlabeled report
     * number in the header, "Rapor Onay Tarihi" as the date label, findings
     * numbered continuously across the colour sections, page furniture
     * between pages, and a declared total in AÇIKLAMALAR.
     */
    private function ek7Text(): string
    {
        return <<<'TEXT'
        Doküman No : MS.FR.23 | Yayın Tarihi : 02.05.2012 | Revizyon No: 16
        EK 7 - ASANSÖR PERİYODİK/TAKİP KONTROL RAPORU
        RoyalCert Belgelendirme ve Gözetim Hizmetleri A.Ş.
        52563D52-EC73-4BEA-9B02-287F8ABA6079 146402649-1
        R.NEV.26.1900
        12.06.2026 (P)
        GÜVENSİZ X
        60 GÜN 11.08.2026
        BİR SONRAKİ PERİYODİK/TAKİP KONTROL TARİHİ 11.08.2026
        AÇIKLAMALAR
        4 Adet Uygunsuzluk Tespit Edilmiştir.
        RAPOR ONAY TARİHİ 12.06.2026
        Kırmızı Eksikler
        1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.
        2 - 4.2.6 Kapı yüksekliği 2,0 m'den az olduğunda uyarılar yapılmalıdır. (ÖLÇ: 195 )
        Sarı Eksikler
        3 - 5.9.1 Kabin içerisinde acil durum aydınlatma düzeni bulunmalıdır.
        RAPOR ONAY TARİHİ
        12.06.2026
        Doküman No : MS.FR.23
        Mavi Eksikler
        4 - 1.11.3 Hareketli parçaların bakım ve kontrolü için serbest yatay alan bulunmalıdır. (ÖLÇ: 15 20 )
        RAPOR ONAY TARİHİ 12.06.2026
        TEXT;
    }

    public function test_ek7_report_number_without_label_is_extracted(): void
    {
        $this->assertSame('R.NEV.26.1900', $this->parser->parse($this->ek7Text(), 'Örnek Evler 3')->reportNumber);
    }

    public function test_ek7_elevator_registration_number_is_extracted(): void
    {
        // "146402649-1" under the header barcode; the barcode UUID and
        // standard references ("3864-1") must not match.
        $this->assertSame(
            '146402649-1',
            $this->parser->parse($this->ek7Text(), 'Örnek Evler 3')->registrationNumber,
        );
    }

    public function test_ek7_rapor_onay_tarihi_is_used_as_inspection_date(): void
    {
        $report = $this->parser->parse($this->ek7Text(), 'Örnek Evler 3');

        $this->assertSame('2026-06-12', $report->inspectedAt);
        $this->assertSame('2026-08-11', $report->nextInspectionDate);
    }

    public function test_ek7_findings_are_extracted_with_severity_code_and_measurement(): void
    {
        $report = $this->parser->parse($this->ek7Text(), 'Örnek Evler 3');

        $this->assertSame('red', $report->label);
        $this->assertSame(4, $report->declaredFindingCount);
        $this->assertCount(4, $report->findings);
        $this->assertNull($report->findingsProblem());

        [$first, $second, $third, $fourth] = $report->findings;

        $this->assertSame(
            ['severity' => 'red', 'position' => 1, 'item_code' => '2.7.8'],
            ['severity' => $first['severity'], 'position' => $first['position'], 'item_code' => $first['item_code']],
        );
        $this->assertSame('Kat kapı kilit muhafazaları takılmalı.', $first['description']);
        $this->assertNull($first['measurement']);

        // Measurement is pulled out of the description.
        $this->assertSame('195', $second['measurement']);
        $this->assertSame("Kapı yüksekliği 2,0 m'den az olduğunda uyarılar yapılmalıdır.", $second['description']);

        $this->assertSame('yellow', $third['severity']);
        // Page furniture between pages is not glued onto the finding text.
        $this->assertSame('Kabin içerisinde acil durum aydınlatma düzeni bulunmalıdır.', $third['description']);

        $this->assertSame('blue', $fourth['severity']);
        $this->assertSame('15 20', $fourth['measurement']);
    }

    public function test_defect_report_without_findings_reports_a_problem(): void
    {
        $report = $this->parser->parse($this->reportText("(P)\nKIRMIZI EKSİKLER\n"), 'Test Apt');

        $this->assertSame('red', $report->label);
        $this->assertNotNull($report->findingsProblem());
    }

    public function test_declared_count_mismatch_reports_a_problem(): void
    {
        $text = $this->reportText(
            "(P)\nKIRMIZI EKSİKLER\n1 - 2.7.8 Kat kapı kilit muhafazaları takılmalı.\n".
            "5 Adet Uygunsuzluk Tespit Edilmiştir.\n",
        );

        $this->assertNotNull($this->parser->parse($text, 'Test Apt')->findingsProblem());
    }
}
