<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_image_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('barcode')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('type')->nullable()->comment('لا توجد صورة / غير موجود في الملف');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_image_reports');
    }
};
