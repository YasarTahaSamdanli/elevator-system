<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elevator_inspections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('elevator_id')->constrained('elevators')->cascadeOnDelete();
            $table->enum('type', ['periodic', 'follow_up'])->default('periodic');
            $table->string('inspection_body')->nullable();
            $table->date('inspected_at');
            $table->enum('label', ['green', 'blue', 'yellow', 'red']);
            $table->string('report_number', 100)->nullable();
            $table->date('follow_up_due_date')->nullable();
            $table->date('next_inspection_date')->nullable();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('elevator_id');
            $table->index('label');
            $table->index('inspected_at');
            $table->index('follow_up_due_date');
            $table->index('next_inspection_date');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elevator_inspections');
    }
};
