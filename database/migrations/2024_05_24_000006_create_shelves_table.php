<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('mall_branches')->onDelete('cascade');
            $table->string('name')->comment('e.g. Shelf A1, Fridge 3');
            $table->string('section')->comment('e.g. Dairy, Electronics');
            $table->json('map_coordinates')->nullable()->comment('Relative position on SVG indoor map');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelves');
    }
};
