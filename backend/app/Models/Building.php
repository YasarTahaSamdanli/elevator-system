<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<BuildingFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * company_id is deliberately excluded: it is always derived from the
     * authenticated user's company (see BelongsToCompany), never from
     * client input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'district',
        'manager_name',
        'manager_phone',
        'entrance_code',
        'access_notes',
        'latitude',
        'longitude',
        'is_active',
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

    public function elevators(): HasMany
    {
        return $this->hasMany(Elevator::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }
}
