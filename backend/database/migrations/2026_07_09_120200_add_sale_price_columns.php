<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer-facing sale prices, separate from cost: unit_price on a
     * work order item stays the purchase-cost snapshot, sale_unit_price
     * is what the customer is charged when the work order completes.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('default_sale_price', 12, 2)->nullable()->after('default_unit_price');
        });

        Schema::table('work_order_items', function (Blueprint $table) {
            $table->decimal('sale_unit_price', 12, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_items', function (Blueprint $table) {
            $table->dropColumn('sale_unit_price');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('default_sale_price');
        });
    }
};
