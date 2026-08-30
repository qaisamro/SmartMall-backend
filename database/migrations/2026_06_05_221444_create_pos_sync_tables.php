<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pos_sync_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->foreignId('mall_id')->constrained('malls')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // creator (PC browser)
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->timestamps();
        });

        Schema::create('pos_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_sync_session_id')->constrained('pos_sync_sessions')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->decimal('price_at_scan', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sync_items');
        Schema::dropIfExists('pos_sync_sessions');
    }
};
