<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Denormalized snapshot of the latest elevator inspection so lists and
     * dashboards can filter/sort without joining. Source of truth stays in
     * elevator_inspections; ElevatorInspection keeps these in sync.
     */
    public function up(): void
    {
        Schema::table('elevators', function (Blueprint $table) {
            $table->string('current_label', 10)->nullable()->after('status');
            $table->date('last_inspection_at')->nullable()->after('current_label');
            $table->date('next_inspection_due')->nullable()->after('last_inspection_at');
            $table->date('follow_up_due')->nullable()->after('next_inspection_due');

            $table->index('current_label');
            $table->index('next_inspection_due');
            $table->index('follow_up_due');
        });
    }

    public function down(): void
    {
        Schema::table('elevators', function (Blueprint $table) {
            $table->dropIndex(['current_label']);
            $table->dropIndex(['next_inspection_due']);
            $table->dropIndex(['follow_up_due']);
            $table->dropColumn(['current_label', 'last_inspection_at', 'next_inspection_due', 'follow_up_due']);
        });
    }
};
