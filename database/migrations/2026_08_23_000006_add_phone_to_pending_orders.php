<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_orders', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('pending_orders', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
