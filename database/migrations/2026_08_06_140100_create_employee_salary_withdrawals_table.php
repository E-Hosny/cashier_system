<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->decimal('amount', 10, 2);
            $table->date('withdrawal_date');
            $table->string('year_month', 7)->index(); // YYYY-MM
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'year_month']);
            $table->index(['employee_id', 'withdrawal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_withdrawals');
    }
};
