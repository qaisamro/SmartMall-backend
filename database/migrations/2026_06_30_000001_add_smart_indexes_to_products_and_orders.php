<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============ PRODUCTS ============

        // Composite: filtering by mall + active status (most common query)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['mall_id', 'is_active'], 'idx_products_mall_active');
        });

        // Composite: browsing products in a mall by category (second most common)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['mall_id', 'category_id', 'is_active'], 'idx_products_mall_cat_active');
        });

        // Composite: barcode lookup within a mall (scanner feature)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['barcode', 'mall_id'], 'idx_products_barcode_mall');
        });

        // Range: sorting/filtering by price (discounts, price range filters)
        Schema::table('products', function (Blueprint $table) {
            $table->index(['price', 'discount_price'], 'idx_products_price_range');
        });

        // Range: recent products / ordering
        Schema::table('products', function (Blueprint $table) {
            $table->index(['created_at'], 'idx_products_created_at');
        });

        // ============ ORDERS ============

        // Composite: store owner viewing their orders by status
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['mall_id', 'status', 'created_at'], 'idx_orders_mall_status_created');
        });

        // Composite: customer viewing their order history
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status_created');
        });

        // Composite: delivery person looking up deliveries
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['delivery_status', 'delivery_user_id'], 'idx_orders_delivery_status_user');
        });

        // ============ ORDER ITEMS ============

        // Composite: lookup items by order (already has FK index, but composite is faster for joins)
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id'], 'idx_order_items_order_product');
        });

        // ============ CATEGORIES ============

        // Composite: categories within a mall, ordered
        Schema::table('categories', function (Blueprint $table) {
            $table->index(['mall_id', 'parent_id', 'order'], 'idx_categories_mall_parent_order');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_mall_active');
            $table->dropIndex('idx_products_mall_cat_active');
            $table->dropIndex('idx_products_barcode_mall');
            $table->dropIndex('idx_products_price_range');
            $table->dropIndex('idx_products_created_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_mall_status_created');
            $table->dropIndex('idx_orders_user_status_created');
            $table->dropIndex('idx_orders_delivery_status_user');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_order_product');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_mall_parent_order');
        });
    }
};
