<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending','in_progress','replied','resolved','rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE complaints MODIFY status ENUM('pending','in_progress','resolved','rejected') DEFAULT 'pending'");
    }
};
