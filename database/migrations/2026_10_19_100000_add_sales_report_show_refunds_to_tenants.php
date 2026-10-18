<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')
            && ! Schema::hasColumn('tenants', 'sales_report_show_refunds')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('sales_report_show_refunds')
                    ->default(false)
                    ->after('closing_fridge_show_in_sales_report');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants')
            && Schema::hasColumn('tenants', 'sales_report_show_refunds')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('sales_report_show_refunds');
            });
        }
    }
};
