<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('endpoint', 500);
            $table->string('auth_key', 100);
            $table->string('p256dh_key', 100);
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->unique('endpoint', 'idx_push_subscriptions_endpoint');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
