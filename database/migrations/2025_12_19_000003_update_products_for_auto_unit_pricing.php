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
            $table->string('purchase_unit', 20)->nullable()->after('unit'); // وحدة الشراء (لتر/كجم/قطعة)
            $table->decimal('purchase_quantity', 10, 3)->nullable()->after('purchase_unit'); // الكمية المشتراة
            $table->string('consume_unit', 20)->nullable()->after('purchase_quantity'); // وحدة الاستهلاك (مللي/جرام/قطعة)
            // حذف الحقول القديمة
            $table->dropColumn(['base_unit', 'base_unit_price', 'unit_conversions']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_unit', 'purchase_quantity', 'consume_unit']);
            $table->string('base_unit')->nullable();
            $table->decimal('base_unit_price', 10, 3)->nullable();
            $table->json('unit_conversions')->nullable();
        });
    }
}; 