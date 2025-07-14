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
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'purchase_unit')) {
                $table->string('purchase_unit', 20)->nullable()->after('size_variants');
            }
            if (!Schema::hasColumn('products', 'purchase_quantity')) {
                $table->decimal('purchase_quantity', 10, 3)->nullable()->after('purchase_unit');
            }
            if (!Schema::hasColumn('products', 'purchase_price')) {
                $table->decimal('purchase_price', 10, 2)->nullable()->after('purchase_quantity');
            }
            if (!Schema::hasColumn('products', 'consume_unit')) {
                $table->string('consume_unit', 20)->nullable()->after('purchase_price');
            }
            if (!Schema::hasColumn('products', 'unit_consume_price')) {
                $table->decimal('unit_consume_price', 10, 4)->nullable()->after('consume_unit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_consume_price')) {
                $table->dropColumn('unit_consume_price');
            }
            if (Schema::hasColumn('products', 'consume_unit')) {
                $table->dropColumn('consume_unit');
            }
            if (Schema::hasColumn('products', 'purchase_price')) {
                $table->dropColumn('purchase_price');
            }
            if (Schema::hasColumn('products', 'purchase_quantity')) {
                $table->dropColumn('purchase_quantity');
            }
            if (Schema::hasColumn('products', 'purchase_unit')) {
                $table->dropColumn('purchase_unit');
            }
        });
    }
}; 