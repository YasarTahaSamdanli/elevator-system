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
        Schema::table('service_contracts', function (Blueprint $table) {
            $table->foreignId('company_id')->after('elevator_id')->constrained('companies')->cascadeOnDelete();
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
