<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Learned mapping from an inspection body's report identity (e.g. the
        // building name RoyalCert puts in the mail subject) to our elevator.
        // One manual match teaches the importer to auto-match forever after.
        Schema::create('inspection_source_elevators', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('source', 50)->default('royalcert');
            $table->string('external_key', 500);
            $table->foreignId('elevator_id')->constrained('elevators')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'source', 'external_key']);
            $table->index('elevator_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_source_elevators');
    }
};
