<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fridge_product_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 64)->default('');
            $table->string('deduct_on_pull', 16)->default('none'); // none | all | custom
            $table->string('deduct_on_sale', 16)->default('none');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'size'], 'fridge_cfg_tenant_product_size');
        });

        Schema::create('fridge_product_ingredient_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fridge_product_config_id')->constrained('fridge_product_configs')->cascadeOnDelete();
            $table->foreignId('raw_material_id')->constrained('products')->cascadeOnDelete();
            $table->boolean('deduct_on_pull')->default(false);
            $table->boolean('deduct_on_sale')->default(false);
            $table->timestamps();

            $table->unique(['fridge_product_config_id', 'raw_material_id'], 'fridge_ing_rule_unique');
        });

        Schema::create('branch_fridge_stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 64)->default('');
            $table->decimal('quantity', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id', 'size'], 'branch_fridge_stock_unique');
        });

        Schema::create('fridge_pending_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('fridge_product_config_id')->constrained('fridge_product_configs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 64)->default('');
            $table->string('label_code', 64)->unique();
            $table->decimal('unit_count', 14, 4);
            $table->string('status', 16)->default('pending');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'from_fridge')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->boolean('from_fridge')->default(false)->after('size');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'from_fridge')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('from_fridge');
            });
        }

        Schema::dropIfExists('fridge_pending_labels');
        Schema::dropIfExists('branch_fridge_stocks');
        Schema::dropIfExists('fridge_product_ingredient_rules');
        Schema::dropIfExists('fridge_product_configs');
    }
};
