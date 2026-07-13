<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ElevatorFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Elevator extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ElevatorFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * company_id is deliberately excluded: it is always derived from the
     * owning building/authenticated user, never from client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'building_id',
        'serial_number',
        'name',
        'manufacturer',
        'model',
        'installation_year',
        'capacity_kg',
        'person_capacity',
        'stop_count',
        'registration_number',
        'status',
        'notes',
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid', 'qr_identifier'];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function serviceContracts(): HasMany
    {
        return $this->hasMany(ServiceContract::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ElevatorInspection::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installation_year' => 'integer',
            'capacity_kg' => 'integer',
            'person_capacity' => 'integer',
            'stop_count' => 'integer',
            'last_inspection_at' => 'date',
            'next_inspection_due' => 'date',
            'follow_up_due' => 'date',
        ];
    }
}
