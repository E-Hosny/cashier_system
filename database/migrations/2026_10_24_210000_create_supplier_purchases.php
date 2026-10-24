<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('suppliers', 'notes')) {
                    $table->text('notes')->nullable()->after('email');
                }
                if (! Schema::hasColumn('suppliers', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('notes');
                }
            });
        }

        if (! Schema::hasTable('supplier_purchases')) {
            Schema::create('supplier_purchases', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->date('purchased_on');
                $table->string('invoice_number')->nullable();
                $table->string('invoice_path');
                $table->string('invoice_original_name')->nullable();
                $table->string('invoice_mime')->nullable();
                $table->text('notes')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'supplier_id', 'purchased_on']);
            });
        }

        if (! Schema::hasTable('supplier_purchase_lines')) {
            Schema::create('supplier_purchase_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('supplier_purchase_id')->constrained('supplier_purchases')->cascadeOnDelete();
                $table->string('line_type', 20);
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('item_name');
                $table->string('unit', 50)->nullable();
                $table->decimal('quantity', 12, 3);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->string('stock_destination', 20)->default('none');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->decimal('stock_quantity_added', 14, 4)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_lines');
        Schema::dropIfExists('supplier_purchases');

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('suppliers', 'is_active')) {
                    $table->dropColumn('is_active');
                }
                if (Schema::hasColumn('suppliers', 'notes')) {
                    $table->dropColumn('notes');
                }
            });
        }
    }
};
