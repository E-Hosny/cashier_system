<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_purchases') && Schema::hasColumn('supplier_purchases', 'invoice_path')) {
            Schema::table('supplier_purchases', function (Blueprint $table) {
                $table->string('invoice_path')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_purchases') && Schema::hasColumn('supplier_purchases', 'invoice_path')) {
            Schema::table('supplier_purchases', function (Blueprint $table) {
                $table->string('invoice_path')->nullable(false)->change();
            });
        }
    }
};
