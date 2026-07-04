<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_discounts', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('reason');
            $table->foreignId('attendance_deduction_rule_id')
                ->nullable()
                ->after('source')
                ->constrained('attendance_deduction_rules')
                ->nullOnDelete();
            $table->foreignId('employee_attendance_id')
                ->nullable()
                ->after('attendance_deduction_rule_id')
                ->constrained('employee_attendances')
                ->nullOnDelete();

            $table->index(['employee_id', 'discount_date', 'source']);
        });

        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_minutes')->nullable()->after('checkin_time');
        });
    }

    public function down(): void
    {
        Schema::table('employee_attendances', function (Blueprint $table) {
            $table->dropColumn('late_minutes');
        });

        Schema::table('employee_discounts', function (Blueprint $table) {
            $table->dropForeign(['attendance_deduction_rule_id']);
            $table->dropForeign(['employee_attendance_id']);
            $table->dropColumn(['source', 'attendance_deduction_rule_id', 'employee_attendance_id']);
        });
    }
};
