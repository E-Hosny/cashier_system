<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('fixed_salary_withdraw_limit_enabled')->default(false)->after('closing_dawn_ends_at');
            $table->decimal('fixed_salary_early_withdraw_percent', 5, 2)->nullable()->default(20)->after('fixed_salary_withdraw_limit_enabled');
            $table->unsignedTinyInteger('fixed_salary_full_unlock_day')->nullable()->default(29)->after('fixed_salary_early_withdraw_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'fixed_salary_withdraw_limit_enabled',
                'fixed_salary_early_withdraw_percent',
                'fixed_salary_full_unlock_day',
            ]);
        });
    }
};
