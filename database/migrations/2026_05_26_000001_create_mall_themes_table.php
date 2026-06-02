<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_id')->unique()->constrained('malls')->cascadeOnDelete();
            $table->string('primary_color', 7)->default('#3b82f6');
            $table->string('secondary_color', 7)->default('#0f172a');
            $table->string('accent_color', 7)->default('#10b981');
            $table->string('background_color', 7)->default('#080810');
            $table->string('text_color', 7)->default('#f8fafc');
            $table->boolean('dark_mode')->default(true);
            $table->string('font_family')->nullable();
            $table->string('border_radius')->default('modern');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_themes');
    }
};
