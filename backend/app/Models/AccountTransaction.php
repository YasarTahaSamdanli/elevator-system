<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AccountTransactionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immutable customer-ledger entry. Never update or delete rows —
 * corrections are reverse entries (adjustment_charge / adjustment_credit),
 * mirroring the stock movement ledger.
 */
class AccountTransaction extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AccountTransactionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    /**
     * Types that increase the building's balance (debt).
     *
     * @var list<string>
     */
    public const CHARGE_TYPES = [
        'opening_balance',
        'maintenance_fee',
        'part_charge',
        'revision_charge',
        'adjustment_charge',
    ];

    /**
     * Types that decrease the building's balance.
     *
     * @var list<string>
     */
    public const CREDIT_TYPES = [
        'payment',
        'adjustment_credit',
    ];

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
        'elevator_id',
        'service_contract_id',
        'type',
        'amount',
        'occurred_at',
        'work_order_id',
        'payment_method_id',
        'collected_by',
        'payer_name',
        'description',
        'created_by',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function serviceContract(): BelongsTo
    {
        return $this->belongsTo(ServiceContract::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Amount with its ledger direction: charges +, payments/credits −. */
    protected function signedAmount(): Attribute
    {
        // amount is a positive decimal string; prefixing '-' avoids float
        // precision loss (and a bcmath dependency).
        return Attribute::get(
            fn (): string => in_array($this->type, self::CREDIT_TYPES, true)
                ? '-'.$this->amount
                : (string) $this->amount,
        );
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AccountTransaction $transaction): void {
            if ($transaction->company_id || ! $transaction->building_id) {
                return;
            }

            $transaction->company_id = Building::withoutGlobalScopes()
                ->whereKey($transaction->building_id)
                ->value('company_id');
        });
    }
}
