<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('service_contract_id')->constrained('service_contracts')->cascadeOnDelete();
            $table->string('work_order_number', 50)->unique();
            $table->enum('type', ['maintenance', 'fault', 'inspection', 'repair']);
            $table->enum('status', ['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->enum('priority', ['low', 'normal', 'high', 'critical'])->default('normal');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('service_contract_id');
            $table->index('assigned_user_id');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('scheduled_at');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
