<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_deduction_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('min_late_minutes');
            $table->unsignedSmallInteger('max_late_minutes')->nullable();
            $table->decimal('deduction_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });

        Schema::create('deduction_rule_employee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_deduction_rule_id');
            $table->unsignedBigInteger('employee_id');
            $table->unique(['attendance_deduction_rule_id', 'employee_id'], 'rule_employee_unique');
            $table->foreign('attendance_deduction_rule_id', 'rule_emp_rule_fk')
                ->references('id')->on('attendance_deduction_rules')->cascadeOnDelete();
            $table->foreign('employee_id', 'rule_emp_employee_fk')
                ->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_rule_employee');
        Schema::dropIfExists('attendance_deduction_rules');
    }
};
