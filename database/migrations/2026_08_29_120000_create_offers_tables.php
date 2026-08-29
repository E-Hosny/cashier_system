<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('offer_price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offer_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('slot_index')->default(0);
            $table->string('rule_type', 32); // fixed_products | category_pick | product_pick
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('offer_rule_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('size', 64)->nullable();
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('from_fridge')->constrained()->nullOnDelete();
            $table->string('offer_bundle_key', 64)->nullable()->after('offer_id');
            $table->decimal('original_unit_price', 10, 2)->nullable()->after('offer_bundle_key');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('offer_id');
            $table->dropColumn(['offer_bundle_key', 'original_unit_price']);
        });

        Schema::dropIfExists('offer_rule_products');
        Schema::dropIfExists('offer_rules');
        Schema::dropIfExists('offers');
    }
};
