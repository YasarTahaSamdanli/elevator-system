<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class StockMovement extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    use HasUuids;

    public const INBOUND_TYPES = ['purchase_in', 'work_order_return', 'transfer_in', 'adjustment_in'];

    public const OUTBOUND_TYPES = ['work_order_out', 'transfer_out', 'adjustment_out'];

    protected $fillable = [
        'material_id',
        'warehouse_id',
        'type',
        'quantity',
        'unit_price',
        'work_order_id',
        'transfer_group_uuid',
        'occurred_at',
        'created_by',
        'note',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signedQuantity(): string
    {
        if (in_array($this->type, self::OUTBOUND_TYPES, true)) {
            return (string) (-1 * (float) $this->quantity);
        }

        return (string) $this->quantity;
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            if (! $movement->created_by && Auth::id()) {
                $movement->created_by = Auth::id();
            }

            if (! $movement->occurred_at) {
                $movement->occurred_at = now();
            }
        });
    }
}
