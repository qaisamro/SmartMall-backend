<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY delivery_method ENUM('in-mall','delivery','direct_purchase','pickup') NOT NULL DEFAULT 'in-mall'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY delivery_method ENUM('in-mall','delivery','direct_purchase') NOT NULL DEFAULT 'in-mall'");
    }
};
