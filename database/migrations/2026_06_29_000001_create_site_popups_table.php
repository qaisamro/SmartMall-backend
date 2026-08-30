<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_popups', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->string('btn_text')->nullable();
            $table->string('btn_url')->nullable();
            $table->string('target_audience')->default('all'); // all, logged_in, guest, customer, mall-owner, supermarket-owner, delivery-person, order-tracker
            $table->string('target_page')->default('all'); // all, home, malls, cart, etc.
            $table->boolean('is_active')->default(false);
            $table->integer('auto_close_seconds')->nullable()->default(0); // 0 = manual close only
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_popups');
    }
};
