<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_addon_options')
            && ! Schema::hasColumn('product_addon_options', 'extra_price')) {
            Schema::table('product_addon_options', function (Blueprint $table) {
                $table->decimal('extra_price', 10, 2)->default(0)->after('addon_product_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_addon_options')
            && Schema::hasColumn('product_addon_options', 'extra_price')) {
            Schema::table('product_addon_options', function (Blueprint $table) {
                $table->dropColumn('extra_price');
            });
        }
    }
};
