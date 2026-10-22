<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employees') && ! Schema::hasColumn('employees', 'hourly_payout_cap')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->decimal('hourly_payout_cap', 10, 2)
                    ->nullable()
                    ->after('hourly_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'hourly_payout_cap')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('hourly_payout_cap');
            });
        }
    }
};
