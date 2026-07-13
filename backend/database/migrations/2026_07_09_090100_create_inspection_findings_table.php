<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_findings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('elevator_inspection_id')->constrained('elevator_inspections')->cascadeOnDelete();
            $table->text('description');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('elevator_inspection_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_findings');
    }
};
