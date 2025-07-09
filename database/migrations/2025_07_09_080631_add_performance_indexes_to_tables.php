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
        // تحسين أداء جدول المنتجات
        Schema::table('products', function (Blueprint $table) {
            $table->index(['type', 'category_id']);
            $table->index('stock');
        });

        // تحسين أداء جدول المكونات
        Schema::table('ingredients', function (Blueprint $table) {
            $table->index(['finished_product_id', 'size']);
            $table->index('raw_material_id');
        });

        // تحسين أداء جدول الطلبات
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index('tenant_id');
        });

        // تحسين أداء جدول عناصر الطلبات
        Schema::table('order_items', function (Blueprint $table) {
            $table->index(['order_id', 'product_id']);
        });

        // تحسين أداء جدول حركات المخزون
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['product_id', 'type']);
            $table->index('related_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['type', 'category_id']);
            $table->dropIndex(['stock']);
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropIndex(['finished_product_id', 'size']);
            $table->dropIndex(['raw_material_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['tenant_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'product_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'type']);
            $table->dropIndex(['related_order_id']);
        });
    }
};
