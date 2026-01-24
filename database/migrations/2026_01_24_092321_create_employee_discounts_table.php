<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete(); // للدعم Multi-Tenancy
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('discount_date'); // تاريخ اليوم الذي يخص الخصم
            $table->decimal('amount', 10, 2); // مبلغ الخصم
            $table->text('reason')->nullable(); // السبب (اختياري)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); // المستخدم الذي أضاف الخصم
            $table->timestamps();
            
            // فهرس لتحسين الأداء
            $table->index(['employee_id', 'discount_date']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_discounts');
    }
};
