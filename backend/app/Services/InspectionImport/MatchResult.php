<?php

namespace App\Services\InspectionImport;

use App\Models\Elevator;

final class MatchResult
{
    private function __construct(
        public readonly ?Elevator $elevator,
        public readonly ?string $via,
        public readonly ?string $failureReason,
    ) {}

    public static function matched(Elevator $elevator, string $via): self
    {
        return new self($elevator, $via, null);
    }

    public static function failed(string $reason): self
    {
        return new self(null, null, $reason);
    }

    public function isMatched(): bool
    {
        return $this->elevator !== null;
    }
}
