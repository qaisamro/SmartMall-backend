<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->integer('total_offers_used')->default(0)->after('offer_limit');
        });

        // Set initial values for existing malls
        DB::statement('UPDATE malls SET total_offers_used = (SELECT COUNT(*) FROM offers WHERE offers.mall_id = malls.id)');
    }

    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn('total_offers_used');
        });
    }
};
