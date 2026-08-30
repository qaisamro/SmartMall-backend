<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcode_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 255)->unique()->index();
            $table->string('link_photo', 500)->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcode_overrides');
    }
};
