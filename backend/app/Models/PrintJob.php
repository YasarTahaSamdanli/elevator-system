<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrintJob extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * A "printing" claim older than this is considered stale: the agent
     * died mid-print and the job becomes claimable again.
     */
    public const STALE_CLAIM_MINUTES = 15;

    /**
     * Mirror the DB defaults so a freshly created model carries them
     * in memory too (resources render the instance, not a re-read row).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'purpose' => 'inspection_report',
        'status' => 'pending',
        'attempts' => 0,
    ];

    /**
     * company_id is deliberately excluded: derived from the source import,
     * never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'inspection_import_id',
        'status',
        'attempts',
        'claimed_at',
        'printed_at',
        'error_message',
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

    /**
     * Statuses may only move forward along this chain; "printing" may also
     * be re-entered from a stale claim.
     *
     * @var array<string, int>
     */
    public const STATUS_ORDER = [
        'pending' => 0,
        'printing' => 1,
        'done' => 2,
        'failed' => 2,
    ];

    public function canTransitionTo(string $status): bool
    {
        if (in_array($this->status, ['done', 'failed'], true)) {
            return false;
        }

        if ($status === 'printing' && $this->status === 'printing') {
            // Re-claim is only allowed once the previous claim went stale.
            return $this->claimed_at === null
                || $this->claimed_at->lte(now()->subMinutes(self::STALE_CLAIM_MINUTES));
        }

        return self::STATUS_ORDER[$status] > self::STATUS_ORDER[$this->status];
    }

    public function inspectionImport(): BelongsTo
    {
        return $this->belongsTo(InspectionImport::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }
}
