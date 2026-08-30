<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_entries', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->decimal('temperature', 4, 1)->nullable();
            $t->string('condition')->nullable();
            $t->string('icon')->nullable();
            $t->decimal('humidity', 4, 1)->nullable();
            $t->decimal('wind_speed', 4, 1)->nullable();
            $t->text('forecast_short')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('fetched_at')->nullable();
            $t->timestamps();
        });

        Schema::create('currency_entries', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->string('code', 10);
            $t->string('name');
            $t->decimal('buy_rate', 10, 4);
            $t->decimal('sell_rate', 10, 4);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('gold_entries', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->string('type'); // 24k, 22k, 21k, 18k, ounce
            $t->decimal('price', 10, 2);
            $t->decimal('change', 8, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamp('fetched_at')->nullable();
            $t->timestamps();
        });

        Schema::create('prayer_times', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->date('date');
            $t->string('fajr');
            $t->string('sunrise');
            $t->string('dhuhr');
            $t->string('asr');
            $t->string('maghrib');
            $t->string('isha');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['city', 'date']);
        });

        Schema::create('pharmacies', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->string('name');
            $t->text('address')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('is_on_duty')->default(false);
            $t->date('duty_date')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('order')->default(0);
            $t->timestamps();
        });

        Schema::create('news_entries', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('summary')->nullable();
            $t->string('source')->nullable();
            $t->string('source_url')->nullable();
            $t->string('image')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('order')->default(0);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->string('type')->default('info'); // info, warning, danger, success
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('icon')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('expires_at')->nullable();
            $t->integer('order')->default(0);
            $t->timestamps();
        });

        Schema::create('road_conditions', function (Blueprint $t) {
            $t->id();
            $t->string('city')->default('غزة');
            $t->string('road_name');
            $t->string('status'); // clear, moderate, heavy, closed
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->integer('order')->default(0);
            $t->timestamps();
        });

        Schema::create('home_sections', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique(); // weather, currencies, gold, prayer_times, pharmacies, news, alerts, road_conditions
            $t->string('label_ar');
            $t->string('label_en')->nullable();
            $t->boolean('is_visible')->default(true);
            $t->integer('sort_order')->default(0);
            $t->string('icon')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_entries');
        Schema::dropIfExists('currency_entries');
        Schema::dropIfExists('gold_entries');
        Schema::dropIfExists('prayer_times');
        Schema::dropIfExists('pharmacies');
        Schema::dropIfExists('news_entries');
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('road_conditions');
        Schema::dropIfExists('home_sections');
    }
};
