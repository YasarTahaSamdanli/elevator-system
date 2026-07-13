<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Scopes\CompanyScope;
use Database\Factories\ElevatorInspectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class ElevatorInspection extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ElevatorInspectionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Regulatory remediation windows per label: defects on a red label must
     * be fixed within 30 days, yellow within 60 (Asansör Periyodik Kontrol
     * Yönetmeliği). Used to suggest follow_up_due_date when not supplied.
     *
     * @var array<string, int>
     */
    public const FOLLOW_UP_DAYS = [
        'red' => 30,
        'yellow' => 60,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * company_id is deliberately excluded: it is always derived from the
     * owning elevator/authenticated user, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'elevator_id',
        'type',
        'inspection_body',
        'inspected_at',
        'label',
        'report_number',
        'follow_up_due_date',
        'next_inspection_date',
        'work_order_id',
        'created_by',
        'notes',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(InspectionFinding::class)->orderBy('id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The import row this inspection was created from, when it came in via
     * the RoyalCert mail/PDF pipeline (carries the stored report PDF).
     */
    public function import(): HasOne
    {
        return $this->hasOne(InspectionImport::class, 'elevator_inspection_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_at' => 'date',
            'follow_up_due_date' => 'date',
            'next_inspection_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ElevatorInspection $inspection): void {
            if ($inspection->follow_up_due_date !== null) {
                return;
            }

            $days = self::FOLLOW_UP_DAYS[$inspection->label] ?? null;

            if ($days !== null && $inspection->inspected_at !== null) {
                $inspection->follow_up_due_date = Carbon::parse($inspection->inspected_at)->addDays($days);
            }
        });

        static::saved(fn (ElevatorInspection $inspection) => self::refreshElevatorLabelCache($inspection->elevator_id));
        static::deleted(fn (ElevatorInspection $inspection) => self::refreshElevatorLabelCache($inspection->elevator_id));
    }

    /**
     * Mirror the latest inspection onto the elevator's denormalized label
     * columns. The latest inspection wins wholesale (including null
     * follow-up), so a passed follow-up inspection naturally clears the
     * previous red/yellow deadline.
     */
    public static function refreshElevatorLabelCache(int $elevatorId): void
    {
        $latest = self::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->where('elevator_id', $elevatorId)
            ->orderByDesc('inspected_at')
            ->orderByDesc('id')
            ->first();

        Elevator::withoutGlobalScope(CompanyScope::class)
            ->whereKey($elevatorId)
            ->update([
                'current_label' => $latest?->label,
                'last_inspection_at' => $latest?->inspected_at,
                'next_inspection_due' => $latest?->next_inspection_date,
                'follow_up_due' => $latest?->follow_up_due_date,
            ]);
    }
}
