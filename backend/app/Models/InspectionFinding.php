<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InspectionFindingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionFinding extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<InspectionFindingFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Severity display order: mirrors the report's colour sections.
     *
     * @var array<string, int>
     */
    public const SEVERITY_ORDER = [
        'red' => 0,
        'yellow' => 1,
        'blue' => 2,
    ];

    protected $fillable = [
        'elevator_inspection_id',
        'description',
        'severity',
        'item_code',
        'position',
        'measurement',
        'is_resolved',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ElevatorInspection::class, 'elevator_inspection_id');
    }

    protected function casts(): array
    {
        return [
            'is_resolved' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InspectionFinding $finding): void {
            if ($finding->company_id || ! $finding->elevator_inspection_id) {
                return;
            }

            $finding->company_id = ElevatorInspection::withoutGlobalScopes()
                ->whereKey($finding->elevator_inspection_id)
                ->value('company_id');
        });
    }
}
