<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit', 50)->nullable()->after('barcode');
        });

        Schema::table('product_imports', function (Blueprint $table) {
            $table->integer('inserted_rows')->default(0)->after('imported_rows');
            $table->integer('updated_rows')->default(0)->after('inserted_rows');
            $table->integer('skipped_rows')->default(0)->after('failed_rows');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('unit');
        });

        Schema::table('product_imports', function (Blueprint $table) {
            $table->dropColumn('inserted_rows');
            $table->dropColumn('updated_rows');
            $table->dropColumn('skipped_rows');
        });
    }
};
