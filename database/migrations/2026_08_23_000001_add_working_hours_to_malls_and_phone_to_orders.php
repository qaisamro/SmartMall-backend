<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->time('open_time')->nullable()->after('location_arabic');
            $table->time('close_time')->nullable()->after('open_time');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_phone', 30)->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_phone');
        });
    }
};
