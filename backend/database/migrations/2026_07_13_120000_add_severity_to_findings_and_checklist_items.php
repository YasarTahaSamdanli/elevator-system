<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RoyalCert EK 7 reports group findings by defect colour (kırmızı/sarı/mavi
 * eksikler) with a report-wide sequence number and a standard item code per
 * line. Carry that structure onto findings and the checklist items copied
 * from them, so the work order can be presented exactly like the paper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_findings', function (Blueprint $table) {
            // red | yellow | blue; null for findings entered by hand.
            $table->string('severity', 10)->nullable()->after('description');
            // Standard item code on the report line, e.g. "2.7.8".
            $table->string('item_code', 20)->nullable()->after('severity');
            // The report-wide sequence number (1..N across all colours).
            $table->unsignedSmallInteger('position')->nullable()->after('item_code');
            // Measured value when the line carries one, e.g. "195" or "15 20".
            $table->string('measurement', 50)->nullable()->after('position');
        });

        Schema::table('work_order_checklist_items', function (Blueprint $table) {
            $table->string('severity', 10)->nullable()->after('label');
            $table->string('item_code', 20)->nullable()->after('severity');
        });

        // Finding descriptions from the report routinely exceed 255 chars.
        Schema::table('work_order_checklist_items', function (Blueprint $table) {
            $table->text('label')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inspection_findings', function (Blueprint $table) {
            $table->dropColumn(['severity', 'item_code', 'position', 'measurement']);
        });

        Schema::table('work_order_checklist_items', function (Blueprint $table) {
            $table->dropColumn(['severity', 'item_code']);
        });

        Schema::table('work_order_checklist_items', function (Blueprint $table) {
            $table->string('label', 255)->change();
        });
    }
};
