<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable customer ledger — the financial twin of stock_movements.
     * Charges (opening_balance, maintenance_fee, part_charge,
     * revision_charge, adjustment_charge) increase the building's balance;
     * payment and adjustment_credit decrease it. Amounts are always
     * positive; direction is derived from type. No updates/deletes —
     * corrections are reverse entries.
     */
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->foreignId('elevator_id')->nullable()->constrained('elevators')->restrictOnDelete();
            // Set on maintenance_fee accruals; used for idempotency (one
            // accrual per contract per month).
            $table->foreignId('service_contract_id')->nullable()->constrained('service_contracts')->restrictOnDelete();
            $table->enum('type', [
                'opening_balance',
                'maintenance_fee',
                'part_charge',
                'revision_charge',
                'adjustment_charge',
                'payment',
                'adjustment_credit',
            ]);
            $table->decimal('amount', 14, 2);
            $table->date('occurred_at');
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payer_name')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('building_id');
            $table->index('elevator_id');
            $table->index('service_contract_id');
            $table->index('type');
            $table->index('occurred_at');
            $table->index('work_order_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
