<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('shelf_id')->constrained('shelves')->onDelete('cascade');
            $table->integer('level')->nullable()->comment('Shelf level/row');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_locations');
    }
};
