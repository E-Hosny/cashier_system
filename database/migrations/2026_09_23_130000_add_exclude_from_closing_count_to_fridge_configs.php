<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fridge_product_configs')
            && ! Schema::hasColumn('fridge_product_configs', 'exclude_from_closing_count')) {
            Schema::table('fridge_product_configs', function (Blueprint $table) {
                $table->boolean('exclude_from_closing_count')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fridge_product_configs')
            && Schema::hasColumn('fridge_product_configs', 'exclude_from_closing_count')) {
            Schema::table('fridge_product_configs', function (Blueprint $table) {
                $table->dropColumn('exclude_from_closing_count');
            });
        }
    }
};
