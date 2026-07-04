<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->time('expected_checkin_time')->nullable()->after('notes');
            $table->time('expected_checkout_time')->nullable()->after('expected_checkin_time');
            $table->unsignedSmallInteger('grace_minutes')->default(0)->after('expected_checkout_time');
            $table->boolean('late_deductions_enabled')->default(true)->after('grace_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'expected_checkin_time',
                'expected_checkout_time',
                'grace_minutes',
                'late_deductions_enabled',
            ]);
        });
    }
};
