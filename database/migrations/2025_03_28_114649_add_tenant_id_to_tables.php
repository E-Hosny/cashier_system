<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['users', 'products', 'orders', 'order_items'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'products', 'orders', 'order_items'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign([$table.'_tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};

