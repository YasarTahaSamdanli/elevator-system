<?php

namespace App\Services\InspectionImport;

/**
 * Pulls structured data out of a RoyalCert inspection report.
 *
 * The label/type heuristics are a direct port of the office's proven Python
 * automation (işgören mail script): the label is derived from which defect
 * sections ("kırmızı/sarı/mavi eksikler") exist in the document, the type
 * from the (P)/(T) markers, and the elevator identity from the mail subject
 * (which RoyalCert sets to the building name). Field-level extraction
 * follows the EK 7 form layout (MS.FR.23): findings are numbered
 * continuously across the colour sections ("1 - 2.7.8 …"), the report
 * number is an unlabeled header cell (R.NEV.26.1900), and the inspection
 * date is labeled "Rapor Onay Tarihi".
 */
class RoyalCertReportParser
{
    /**
     * Phrases that must appear for the document to be treated as an
     * elevator inspection report at all (guards against arbitrary PDFs).
     */
    private const REPORT_MARKERS = ['asansor', 'periyodik kontrol', 'muayene', 'denetim raporu'];

    /**
     * Colour section headers, worst first. Character classes cover every way
     * a PDF text layer may decode Turkish İ/ı (the Python script hit both).
     */
    private const SECTION_PATTERNS = [
        'red' => '/K[Iıİi]RM[Iıİi]Z[Iıİi]\s+EKS[Iıİi]KLER/iu',
        'yellow' => '/SAR[Iıİi]\s+EKS[Iıİi]KLER/iu',
        'blue' => '/MAV[Iıİi]\s+EKS[Iıİi]KLER/iu',
    ];

    /**
     * Page furniture that repeats on every printed page; a finding
     * description never runs past the first of these.
     */
    private const FOOTER_PATTERNS = [
        '/RAPOR\s+ONAY\s+TAR[Iıİi]H[Iıİi]/iu',
        '/MUAYENE\s+M[UÜü]HEND[Iıİi]S[Iıİi]/iu',
        '/TEKN[Iıİi]K\s+Y[OÖö]NET[Iıİi]C[Iıİi]/iu',
        '/AD[Iıİi]\s*\/\s*SOYAD[Iıİi]/iu',
        '/DOK[UÜü]MAN\s+NO/iu',
        '/EK\s*7\s*[-–]/iu',
        '/ROYALCERT/iu',
        '/[Iıİi][ÇCçc]ERENK[OÖö]Y/iu',
        '/A[ÇCçc]IKLAMALAR/iu',
    ];

    public function parse(string $text, ?string $mailSubject = null): ParsedReport
    {
        $normalized = self::normalizeForSearch($text);
        $warnings = [];

        if (! $this->looksLikeReport($normalized)) {
            return new ParsedReport(
                identity: $this->extractIdentity($mailSubject),
                identityNormalized: null,
                warnings: ['Document does not look like an elevator inspection report.'],
            );
        }

        $label = $this->extractLabel($normalized);
        $type = $this->detectType($normalized, $warnings);
        $identity = $this->extractIdentity($mailSubject);

        if ($identity === null) {
            $warnings[] = 'No elevator identity found (empty mail subject).';
        }

        $reportNumber = $this->extractReportNumber($text);
        $registrationNumber = $this->extractRegistrationNumber($text);
        $inspectedAt = $this->extractDateAfter($text, ['rapor onay tarihi', 'kontrol tarihi', 'muayene tarihi', 'deney tarihi']);
        $nextInspectionDate = $this->extractDateAfter($text, ['sonraki kontrol', 'bir sonraki periyodik', 'gelecek kontrol']);
        $findings = $this->extractFindings($text);
        $declaredFindingCount = $this->extractDeclaredFindingCount($normalized);

        if ($inspectedAt === null) {
            $warnings[] = 'Inspection date not found in document; mail date will be used.';
        }

        if ($declaredFindingCount !== null && $declaredFindingCount !== count($findings)) {
            $warnings[] = sprintf(
                'Report declares %d findings but %d were extracted.',
                $declaredFindingCount,
                count($findings),
            );
        }

        if ($label !== 'green' && $findings === []) {
            $warnings[] = sprintf('Report label is %s but no findings could be extracted.', $label);
        }

        return new ParsedReport(
            reportNumber: $reportNumber,
            inspectedAt: $inspectedAt,
            label: $label,
            type: $type,
            nextInspectionDate: $nextInspectionDate,
            identity: $identity,
            identityNormalized: $identity !== null ? self::normalizeIdentity($identity) : null,
            registrationNumber: $registrationNumber,
            findings: $findings,
            declaredFindingCount: $declaredFindingCount,
            warnings: $warnings,
        );
    }

    /**
     * The office Python script's C-criterion: the report lists a section per
     * defect colour, so the worst colour present is the label. No sections
     * at all means a clean (green) report.
     */
    private function extractLabel(string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'kirmizi eksikler') => 'red',
            str_contains($normalized, 'sari eksikler') => 'yellow',
            str_contains($normalized, 'mavi eksikler') => 'blue',
            default => 'green',
        };
    }

    /**
     * The Python script's B-criterion: "(p)" marks a periodic inspection,
     * "(p)" together with "(t)" marks a follow-up (takip) inspection.
     *
     * @param  list<string>  $warnings
     */
    private function detectType(string $normalized, array &$warnings): string
    {
        $hasP = str_contains($normalized, '(p)');
        $hasT = str_contains($normalized, '(t)');

        if ($hasP && $hasT) {
            return 'follow_up';
        }

        if (! $hasP) {
            $warnings[] = 'Type markers (P)/(T) not found; assuming periodic.';
        }

        return 'periodic';
    }

    /**
     * RoyalCert puts the building name in the mail subject; port of the
     * Python temizle() cleanup.
     */
    private function extractIdentity(?string $mailSubject): ?string
    {
        if ($mailSubject === null) {
            return null;
        }

        $identity = str_ireplace(['asansör denetim raporu', 'asansor denetim raporu'], '', $mailSubject);
        $identity = preg_replace('/[\\\\\/*?:"<>|]/u', '', $identity);
        $identity = trim((string) $identity);

        return $identity === '' ? null : $identity;
    }

    private function extractReportNumber(string $text): ?string
    {
        if (preg_match('/rapor\s*no\s*[:.]?\s*([A-Z0-9][A-Z0-9\-\/.]{2,40})/iu', $text, $m) === 1) {
            return trim($m[1], '.');
        }

        // EK 7 header cell has no label, just the bare number: R.NEV.26.1900
        if (preg_match('/\bR\.[A-ZÇĞİÖŞÜ]{2,5}\.\d{2}\.\d{2,6}\b/u', $text, $m) === 1) {
            return $m[0];
        }

        return null;
    }

    /**
     * The elevator's registration/identity number printed under the header
     * barcode ("146402649-1"): long digit run, dash, short suffix. Nothing
     * else on the form fits that shape (dates use dots, standard references
     * like "3864-1" have short digit runs, phone numbers are spaced).
     */
    private function extractRegistrationNumber(string $text): ?string
    {
        if (preg_match('/\b(\d{6,12}-\d{1,3})\b/', $text, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Findings sit under the colour section headers and are numbered
     * continuously across sections: "1 - 2.7.8 Kat kapı kilit muhafazaları
     * takılmalı. (ÖLÇ: 195)". A section runs until the next section header;
     * a finding runs until the next finding number or page furniture.
     *
     * @return list<array{severity: string, position: int, item_code: string, description: string, measurement: string|null}>
     */
    private function extractFindings(string $text): array
    {
        $sections = [];

        foreach (self::SECTION_PATTERNS as $severity => $pattern) {
            if (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) === 1) {
                $sections[] = [
                    'severity' => $severity,
                    'header_start' => $m[0][1],
                    'content_start' => $m[0][1] + strlen($m[0][0]),
                ];
            }
        }

        if ($sections === []) {
            return [];
        }

        usort($sections, fn (array $a, array $b) => $a['header_start'] <=> $b['header_start']);

        $findings = [];

        foreach ($sections as $i => $section) {
            $end = $sections[$i + 1]['header_start'] ?? strlen($text);
            $slice = substr($text, $section['content_start'], $end - $section['content_start']);

            foreach ($this->parseFindingSlice($slice, $section['severity']) as $finding) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * @return list<array{severity: string, position: int, item_code: string, description: string, measurement: string|null}>
     */
    private function parseFindingSlice(string $slice, string $severity): array
    {
        // "12 - 1.2.11 …": report-wide sequence number, dash, standard item
        // code (always dotted, so a bare measurement like "195" can't match).
        $matched = preg_match_all(
            '/(?<!\d)(\d{1,3})\s*[-–—]\s*((?:\d+\.)+\d+)\s*/u',
            $slice,
            $starts,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
        );

        if ($matched === false || $matched === 0) {
            return [];
        }

        $findings = [];

        foreach ($starts as $i => $start) {
            $from = $start[0][1] + strlen($start[0][0]);
            $to = $starts[$i + 1][0][1] ?? strlen($slice);
            $description = substr($slice, $from, $to - $from);

            $description = $this->cutAtPageFurniture($description);
            $measurement = null;

            // Pull the trailing measurement out: "(ÖLÇ: 15 20)" / "(OLC: 0)".
            if (preg_match('/\(\s*[ÖO]L[ÇC]\s*[:.]?\s*([^)]*)\)/iu', $description, $m) === 1) {
                $measurement = trim($m[1]) === '' ? null : trim($m[1]);
                $description = str_replace($m[0], '', $description);
            }

            $description = trim((string) preg_replace('/\s+/u', ' ', $description));

            if ($description === '') {
                continue;
            }

            $findings[] = [
                'severity' => $severity,
                'position' => (int) $start[1][0],
                'item_code' => $start[2][0],
                'description' => $description,
                'measurement' => $measurement,
            ];
        }

        return $findings;
    }

    /**
     * Findings span printed pages, so the repeating page header/footer can
     * be glued onto the last finding of a page; cut at the first marker.
     */
    private function cutAtPageFurniture(string $description): string
    {
        $cut = strlen($description);

        foreach (self::FOOTER_PATTERNS as $pattern) {
            if (preg_match($pattern, $description, $m, PREG_OFFSET_CAPTURE) === 1) {
                $cut = min($cut, $m[0][1]);
            }
        }

        return substr($description, 0, $cut);
    }

    /**
     * The AÇIKLAMALAR paragraph states the total: "61 Adet Uygunsuzluk
     * Tespit Edilmiştir" — used to sanity-check the extraction.
     */
    private function extractDeclaredFindingCount(string $normalized): ?int
    {
        if (preg_match('/(\d+)\s*adet\s+uygunsuzluk/u', $normalized, $m) === 1) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Find a dd.mm.yyyy / dd/mm/yyyy / dd-mm-yyyy date shortly after one of
     * the given anchor phrases; returns Y-m-d.
     *
     * @param  list<string>  $anchors
     */
    private function extractDateAfter(string $text, array $anchors): ?string
    {
        $normalized = self::normalizeForSearch($text);

        foreach ($anchors as $anchor) {
            $pos = mb_strpos($normalized, self::normalizeForSearch($anchor));

            if ($pos === false) {
                continue;
            }

            $window = mb_substr($normalized, $pos, 120);

            if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $window, $m) === 1) {
                if (checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                    return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                }
            }
        }

        return null;
    }

    private function looksLikeReport(string $normalized): bool
    {
        foreach (self::REPORT_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Canonical form used when matching a report identity against our
     * records (building names, learned mappings): uppercase would trip over
     * Turkish İ/ı, so fold to lowercase ASCII and squash whitespace. The
     * exact same function runs on both sides of every comparison.
     */
    public static function normalizeIdentity(string $value): string
    {
        $normalized = self::normalizeForSearch($value);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim((string) $normalized);
    }

    /**
     * Lowercase and fold Turkish characters to ASCII so the same keyword
     * search matches regardless of how the PDF library decoded the text
     * (the Python script had to search both "kırmızı" and "kirmizi").
     */
    public static function normalizeForSearch(string $value): string
    {
        $value = str_replace(
            ['İ', 'I', 'Ç', 'Ğ', 'Ö', 'Ş', 'Ü', 'ı', 'ç', 'ğ', 'ö', 'ş', 'ü'],
            ['i', 'i', 'c', 'g', 'o', 's', 'u', 'i', 'c', 'g', 'o', 's', 'u'],
            $value,
        );

        $value = mb_strtolower($value, 'UTF-8');

        // Strip the combining dot mb_strtolower leaves behind for "İ".
        return str_replace("\u{0307}", '', $value);
    }
}
