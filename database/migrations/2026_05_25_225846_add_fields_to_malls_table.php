<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('id');
            $table->string('qr_code_path')->nullable()->after('slug');
            $table->string('cover_image')->nullable()->after('logo');
            $table->text('description')->nullable()->after('name_en');
        });
    }

    public function down(): void
    {
        Schema::table('malls', function (Blueprint $table) {
            $table->dropColumn(['slug', 'qr_code_path', 'cover_image', 'description']);
        });
    }
};
