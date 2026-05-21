<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('name');
            $table->string('unit', 50)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('purchase_kind', 20)->default('raw')->after('tenant_id');
            $table->foreignId('custom_purchase_item_id')
                ->nullable()
                ->after('purchase_kind')
                ->constrained('custom_purchase_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['custom_purchase_item_id']);
            $table->dropColumn(['purchase_kind', 'custom_purchase_item_id']);
        });

        Schema::dropIfExists('custom_purchase_items');
    }
};
