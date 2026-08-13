<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('status', 32)->default('in_progress'); // in_progress | completed | cancelled
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('counted_items_count')->default(0);
            $table->decimal('total_surplus_qty', 14, 4)->default(0);
            $table->decimal('total_shortage_qty', 14, 4)->default(0);
            $table->decimal('total_surplus_value', 14, 2)->default(0);
            $table->decimal('total_shortage_value', 14, 2)->default(0);
            $table->decimal('net_diff_value', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('product_name')->nullable();
            $table->string('unit')->nullable();
            $table->string('consume_unit')->nullable();
            $table->decimal('quantity_per_unit', 14, 4)->nullable();
            $table->decimal('system_qty', 14, 4)->default(0); // consume units snapshot
            $table->decimal('counted_qty', 14, 4)->nullable(); // consume units
            $table->decimal('diff_qty', 14, 4)->nullable();
            $table->decimal('unit_cost', 14, 6)->default(0); // unit_consume_price snapshot
            $table->decimal('diff_value', 14, 2)->nullable();
            $table->boolean('is_counted')->default(false);
            $table->timestamp('counted_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['inventory_count_id', 'product_id']);
            $table->index(['inventory_count_id', 'is_counted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
    }
};
