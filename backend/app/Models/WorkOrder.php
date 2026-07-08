<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<WorkOrderFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * company_id is deliberately excluded: it is always derived from the
     * owning service contract/authenticated user, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'service_contract_id',
        'type',
        'status',
        'priority',
        'scheduled_at',
        'started_at',
        'completed_at',
        'assigned_user_id',
        'description',
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

    /**
     * Lifecycle order of the non-terminal statuses. Transitions may only
     * move forward along this chain (skipping steps is allowed);
     * "cancelled" is reachable from every non-terminal status.
     *
     * @var array<string, int>
     */
    public const STATUS_ORDER = [
        'draft' => 0,
        'planned' => 1,
        'assigned' => 2,
        'in_progress' => 3,
        'completed' => 4,
    ];

    public function canTransitionTo(string $status): bool
    {
        if ($status === $this->status) {
            return true;
        }

        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ($status === 'cancelled') {
            return true;
        }

        return self::STATUS_ORDER[$status] > self::STATUS_ORDER[$this->status];
    }

    public function serviceContract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('position');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)->with('material')->orderBy('id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $workOrder): void {
            if ($workOrder->work_order_number) {
                return;
            }

            $workOrder->work_order_number = self::generateWorkOrderNumber();
        });
    }

    private static function generateWorkOrderNumber(): string
    {
        return 'WO-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
