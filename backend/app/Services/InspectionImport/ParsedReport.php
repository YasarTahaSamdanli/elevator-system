<?php

namespace App\Services\InspectionImport;

/**
 * Snapshot of everything the parser could pull out of one report PDF.
 * Stored verbatim on the import row (parsed_payload) so a human can see
 * what the machine saw, even when parsing partially failed.
 */
final class ParsedReport
{
    /**
     * @param  string|null  $inspectedAt  Y-m-d
     * @param  string|null  $nextInspectionDate  Y-m-d
     * @param  list<array{severity: string, position: int, item_code: string, description: string, measurement: string|null}>  $findings
     * @param  int|null  $declaredFindingCount  the "N Adet Uygunsuzluk" total printed on the report
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly ?string $reportNumber = null,
        public readonly ?string $inspectedAt = null,
        public readonly ?string $label = null,
        public readonly string $type = 'periodic',
        public readonly string $inspectionBody = 'RoyalCert',
        public readonly ?string $nextInspectionDate = null,
        public readonly ?string $identity = null,
        public readonly ?string $identityNormalized = null,
        public readonly ?string $registrationNumber = null,
        public readonly array $findings = [],
        public readonly ?int $declaredFindingCount = null,
        public readonly array $warnings = [],
    ) {}

    /**
     * The minimum the pipeline needs to import automatically: a label and
     * something to match an elevator with. Everything else has fallbacks.
     */
    public function isComplete(): bool
    {
        return $this->label !== null && $this->identityNormalized !== null;
    }

    /**
     * A defect report whose findings list doesn't hold up must be reviewed
     * by a human instead of silently producing a hollow work order. Returns
     * the reason, or null when the findings are trustworthy.
     */
    public function findingsProblem(): ?string
    {
        if ($this->label !== null && $this->label !== 'green' && $this->findings === []) {
            return sprintf('Report label is %s but no findings could be extracted.', $this->label);
        }

        if ($this->declaredFindingCount !== null && $this->declaredFindingCount !== count($this->findings)) {
            return sprintf(
                'Report declares %d findings but %d were extracted.',
                $this->declaredFindingCount,
                count($this->findings),
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'report_number' => $this->reportNumber,
            'inspected_at' => $this->inspectedAt,
            'label' => $this->label,
            'type' => $this->type,
            'inspection_body' => $this->inspectionBody,
            'next_inspection_date' => $this->nextInspectionDate,
            'identity' => $this->identity,
            'identity_normalized' => $this->identityNormalized,
            'registration_number' => $this->registrationNumber,
            'findings' => $this->findings,
            'declared_finding_count' => $this->declaredFindingCount,
            'warnings' => $this->warnings,
        ];
    }
}
