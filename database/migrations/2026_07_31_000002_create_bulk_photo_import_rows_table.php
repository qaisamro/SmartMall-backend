<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_photo_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_photo_import_id')->constrained('bulk_photo_imports')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->text('link_photo')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('section_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('mall_name')->nullable();
            $table->string('status')->default('skipped');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_photo_import_rows');
    }
};
