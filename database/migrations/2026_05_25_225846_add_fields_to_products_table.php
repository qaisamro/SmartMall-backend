<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('hide_stock_from_customer')->default(true)->after('stock_quantity');
            $table->string('shelf_location')->nullable()->after('hide_stock_from_customer');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hide_stock_from_customer', 'shelf_location']);
        });
    }
};
