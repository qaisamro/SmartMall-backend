<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('price_quarterly', 10, 2)->after('price_monthly')->default(500.00);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_period')->after('plan_id')->default('monthly'); // monthly, quarterly, yearly
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('price_quarterly');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });
    }
};
