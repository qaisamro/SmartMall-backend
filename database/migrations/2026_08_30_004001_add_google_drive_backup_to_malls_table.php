<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->string('google_drive_backup_file_id')->nullable()->after('suspended_reason');
            $table->string('google_drive_backup_filename')->nullable()->after('google_drive_backup_file_id');
            $table->timestamp('google_drive_backup_at')->nullable()->after('google_drive_backup_filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn(['google_drive_backup_file_id', 'google_drive_backup_filename', 'google_drive_backup_at']);
        });
    }
};
