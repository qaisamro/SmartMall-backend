<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->boolean('enable_quantity_system')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn('enable_quantity_system');
        });
    }
};
