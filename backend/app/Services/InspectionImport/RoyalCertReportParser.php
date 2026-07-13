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
 * (report number, dates, findings) is best-effort until real sample PDFs
 * are added as fixtures.
 */
class RoyalCertReportParser
{
    /**
     * Phrases that must appear for the document to be treated as an
     * elevator inspection report at all (guards against arbitrary PDFs).
     */
    private const REPORT_MARKERS = ['asansor', 'periyodik kontrol', 'muayene', 'denetim raporu'];

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
        $inspectedAt = $this->extractDateAfter($text, ['kontrol tarihi', 'muayene tarihi', 'deney tarihi']);
        $nextInspectionDate = $this->extractDateAfter($text, ['sonraki kontrol', 'bir sonraki periyodik kontrol', 'gelecek kontrol']);

        if ($inspectedAt === null) {
            $warnings[] = 'Inspection date not found in document; mail date will be used.';
        }

        return new ParsedReport(
            reportNumber: $reportNumber,
            inspectedAt: $inspectedAt,
            label: $label,
            type: $type,
            nextInspectionDate: $nextInspectionDate,
            identity: $identity,
            identityNormalized: $identity !== null ? self::normalizeIdentity($identity) : null,
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
