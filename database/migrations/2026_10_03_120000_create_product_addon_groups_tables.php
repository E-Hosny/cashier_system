<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_addon_groups')) {
            Schema::create('product_addon_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('name')->default('إضافات إجبارية');
                $table->unsignedSmallInteger('required_count')->default(1);
                $table->string('size')->nullable(); // null = كل أحجام المنتج الرئيسي
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['tenant_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('product_addon_options')) {
            Schema::create('product_addon_options', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('product_addon_groups')->cascadeOnDelete();
                $table->foreignId('addon_product_id')->constrained('products')->cascadeOnDelete();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['group_id', 'addon_product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_addon_options');
        Schema::dropIfExists('product_addon_groups');
    }
};
