<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('attendance_group_code', 100)->nullable()->after('attendance_dependency_employee_id');
            $table->unsignedTinyInteger('attendance_group_max_present')->nullable()->after('attendance_group_code');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['attendance_group_code', 'attendance_group_max_present']);
        });
    }
};

