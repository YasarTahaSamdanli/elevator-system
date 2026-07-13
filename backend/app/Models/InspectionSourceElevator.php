<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InspectionSourceElevatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Learned mapping between an inspection body's report identity (normalized
 * building name / registration number from the report) and our elevator.
 * Rows are written when an operator manually matches an import, so the
 * importer auto-matches the same identity from then on.
 */
class InspectionSourceElevator extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<InspectionSourceElevatorFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * company_id is deliberately excluded: always derived from the matched
     * elevator, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'external_key',
        'elevator_id',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }
}
