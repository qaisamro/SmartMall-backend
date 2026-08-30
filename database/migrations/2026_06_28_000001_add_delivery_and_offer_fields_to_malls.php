<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(false)->after('is_active');
            $table->integer('offer_limit')->default(0)->after('delivery_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn(['delivery_enabled', 'offer_limit']);
        });
    }
};
