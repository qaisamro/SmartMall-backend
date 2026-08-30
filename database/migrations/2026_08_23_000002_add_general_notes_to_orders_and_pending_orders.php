<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('total');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('general_notes')->nullable()->after('delivery_phone');
        });
    }

    public function down(): void
    {
        Schema::table('pending_orders', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('general_notes');
        });
    }
};
