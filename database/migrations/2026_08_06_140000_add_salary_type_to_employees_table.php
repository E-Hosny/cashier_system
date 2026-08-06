<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('salary_type', 20)->default('hourly')->after('hourly_rate');
            $table->decimal('fixed_salary', 10, 2)->nullable()->after('salary_type');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['salary_type', 'fixed_salary']);
        });
    }
};
