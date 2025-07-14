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
            // إضافة حقول التسعير للمواد الخام
            $table->string('base_unit')->nullable()->after('unit'); // الوحدة الأساسية (مثل: لتر، كجم)
            $table->decimal('base_unit_price', 10, 3)->nullable()->after('base_unit'); // سعر الوحدة الأساسية
            $table->json('unit_conversions')->nullable()->after('base_unit_price'); // معاملات التحويل للوحدات الأخرى
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['base_unit', 'base_unit_price', 'unit_conversions']);
        });
    }
}; 