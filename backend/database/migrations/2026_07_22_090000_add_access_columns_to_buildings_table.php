<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saha erişim bilgileri: teknisyen binaya vardığında kapıyı açmak ve
     * doğru girişi bulmak için ihtiyaç duyduğu, adresten ayrı tutulan veri.
     */
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('entrance_code', 50)->nullable()->after('manager_phone');
            $table->text('access_notes')->nullable()->after('entrance_code');
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['entrance_code', 'access_notes']);
        });
    }
};
