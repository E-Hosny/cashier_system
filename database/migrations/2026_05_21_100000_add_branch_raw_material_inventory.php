<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_raw_material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('stock', 14, 4)->default(0);
            $table->decimal('stock_alert_threshold', 14, 4)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        Schema::table('raw_material_pending_labels', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained('branches')->nullOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('raw_material_pending_labels', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::dropIfExists('branch_raw_material_stocks');
    }
};
