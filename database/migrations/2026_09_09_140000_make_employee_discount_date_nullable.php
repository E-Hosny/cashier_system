<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_discounts', function (Blueprint $table) {
            $table->date('discount_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('employee_discounts')
            ->whereNull('discount_date')
            ->update(['discount_date' => now()->toDateString()]);

        Schema::table('employee_discounts', function (Blueprint $table) {
            $table->date('discount_date')->nullable(false)->change();
        });
    }
};
