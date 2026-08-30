<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY delivery_status ENUM('none','pending','preparing','ready','accepted','delivering','delivered','failed','direct') NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY delivery_status ENUM('none','pending','preparing','accepted','delivering','delivered','failed','direct') NULL DEFAULT 'pending'");
    }
};
