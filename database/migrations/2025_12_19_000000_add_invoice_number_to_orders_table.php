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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->after('status');
        });
        
        // إضافة فهرس فريد لرقم الفاتورة لمنع التكرار
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('invoice_number', 'orders_invoice_number_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_invoice_number_unique');
            $table->dropColumn('invoice_number');
        });
    }
}; 