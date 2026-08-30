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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('delivery_method', ['in-mall', 'delivery'])->default('in-mall')->after('mall_id');
            $table->enum('delivery_status', ['none', 'pending', 'accepted', 'delivering', 'delivered', 'failed'])->default('none')->after('delivery_method');
            $table->foreignId('delivery_user_id')->nullable()->after('delivery_status')->constrained('users')->onDelete('set null');
            $table->timestamp('delivery_accepted_at')->nullable()->after('delivery_user_id');
            $table->timestamp('delivered_at')->nullable()->after('delivery_accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['delivery_user_id']);
            $table->dropColumn(['delivery_method', 'delivery_status', 'delivery_user_id', 'delivery_accepted_at', 'delivered_at']);
        });
    }
};
