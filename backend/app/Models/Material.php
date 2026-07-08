<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Material extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'unit',
        'category',
        'min_stock_level',
        'default_unit_price',
        'is_active',
        'notes',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function workOrderItems(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'min_stock_level' => 'decimal:3',
            'default_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
