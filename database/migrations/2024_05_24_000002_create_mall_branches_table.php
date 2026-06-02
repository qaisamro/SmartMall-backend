<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mall_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mall_id')->constrained('malls')->onDelete('cascade');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('address_ar');
            $table->string('address_en');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mall_branches');
    }
};
