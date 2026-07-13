<?php

namespace App\Services\InspectionImport;

use App\Models\Building;
use App\Models\Elevator;
use App\Models\InspectionImport;
use App\Models\InspectionSourceElevator;
use App\Models\Scopes\CompanyScope;

/**
 * Resolves a parsed report to one of our elevators. Runs in console context
 * (no Auth, CompanyScope inactive), so every query scopes company_id
 * explicitly. Never guesses: anything ambiguous fails into the review queue.
 */
class ElevatorMatcher
{
    public function match(int $companyId, ParsedReport $report): MatchResult
    {
        if ($report->identityNormalized === null && $report->registrationNumber === null) {
            return MatchResult::failed(InspectionImport::REVIEW_ELEVATOR_NOT_FOUND);
        }

        if ($report->identityNormalized !== null) {
            $mapped = $this->matchViaLearnedMapping($companyId, $report->identityNormalized);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        if ($report->registrationNumber !== null) {
            $byRegistration = $this->matchViaColumn($companyId, 'registration_number', $report->registrationNumber);

            if ($byRegistration !== null) {
                return $byRegistration;
            }
        }

        if ($report->identityNormalized !== null) {
            $byBuilding = $this->matchViaBuildingName($companyId, $report->identityNormalized);

            if ($byBuilding !== null) {
                return $byBuilding;
            }
        }

        return MatchResult::failed(InspectionImport::REVIEW_ELEVATOR_NOT_FOUND);
    }

    private function matchViaLearnedMapping(int $companyId, string $identityNormalized): ?MatchResult
    {
        $mapping = InspectionSourceElevator::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('source', 'royalcert')
            ->where('external_key', $identityNormalized)
            ->first();

        if ($mapping === null) {
            return null;
        }

        $elevator = Elevator::withoutGlobalScope(CompanyScope::class)->find($mapping->elevator_id);

        return $elevator !== null ? MatchResult::matched($elevator, 'mapping') : null;
    }

    private function matchViaColumn(int $companyId, string $column, string $value): ?MatchResult
    {
        $elevators = Elevator::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where($column, $value)
            ->limit(2)
            ->get();

        return match ($elevators->count()) {
            0 => null,
            1 => MatchResult::matched($elevators->first(), $column),
            default => MatchResult::failed(InspectionImport::REVIEW_MULTIPLE_MATCHES),
        };
    }

    /**
     * RoyalCert's mail subject carries the building name; if exactly one of
     * our buildings normalizes to the same string and that building has
     * exactly one elevator, the report is unambiguous.
     */
    private function matchViaBuildingName(int $companyId, string $identityNormalized): ?MatchResult
    {
        $buildingIds = Building::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->pluck('name', 'id')
            ->filter(fn (string $name) => RoyalCertReportParser::normalizeIdentity($name) === $identityNormalized)
            ->keys();

        if ($buildingIds->isEmpty()) {
            return null;
        }

        $elevators = Elevator::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->whereIn('building_id', $buildingIds)
            ->limit(2)
            ->get();

        return match ($elevators->count()) {
            0 => null,
            1 => MatchResult::matched($elevators->first(), 'building_name'),
            default => MatchResult::failed(InspectionImport::REVIEW_MULTIPLE_MATCHES),
        };
    }
}
