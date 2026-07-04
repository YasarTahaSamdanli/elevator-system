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
        Schema::create('elevators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_id')->constrained('buildings')->cascadeOnDelete();
            $table->string('serial_number', 100);
            $table->uuid('qr_identifier')->unique();
            $table->string('name', 150)->nullable();
            $table->string('manufacturer', 150)->nullable();
            $table->string('model', 150)->nullable();
            $table->unsignedSmallInteger('installation_year')->nullable();
            $table->unsignedSmallInteger('capacity_kg')->nullable();
            $table->unsignedSmallInteger('person_capacity')->nullable();
            $table->unsignedSmallInteger('stop_count')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance', 'out_of_service'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('building_id');
            $table->index('serial_number');
            $table->index('registration_number');
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elevators');
    }
};
