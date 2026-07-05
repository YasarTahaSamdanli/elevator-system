<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ServiceContractFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceContract extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ServiceContractFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

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
        'contract_number',
        'start_date',
        'end_date',
        'status',
        'monthly_fee',
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

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_fee' => 'decimal:2',
        ];
    }
}
