<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')
            && ! Schema::hasColumn('tenants', 'closing_fridge_show_in_sales_report')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('closing_fridge_show_in_sales_report')
                    ->default(false)
                    ->after('closing_dawn_ends_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants')
            && Schema::hasColumn('tenants', 'closing_fridge_show_in_sales_report')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('closing_fridge_show_in_sales_report');
            });
        }
    }
};
