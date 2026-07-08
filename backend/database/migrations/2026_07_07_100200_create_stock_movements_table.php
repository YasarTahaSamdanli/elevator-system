<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('type', [
                'purchase_in',
                'work_order_out',
                'work_order_return',
                'transfer_in',
                'transfer_out',
                'adjustment_in',
                'adjustment_out',
            ]);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->uuid('transfer_group_uuid')->nullable();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('material_id');
            $table->index('warehouse_id');
            $table->index('type');
            $table->index('work_order_id');
            $table->index('transfer_group_uuid');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
