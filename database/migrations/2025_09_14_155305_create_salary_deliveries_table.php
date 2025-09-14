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
        Schema::create('salary_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('salary_date'); // التاريخ الذي يخص الراتب (يوم العمل)
            $table->decimal('hours_worked', 8, 2)->default(0); // عدد ساعات العمل
            $table->decimal('hourly_rate', 8, 2); // سعر الساعة وقت التسليم
            $table->decimal('total_amount', 10, 2); // إجمالي المبلغ المستحق
            $table->enum('status', ['pending', 'delivered'])->default('pending'); // حالة التسليم
            $table->datetime('delivered_at')->nullable(); // وقت التسليم
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete(); // من قام بالتسليم
            $table->text('notes')->nullable(); // ملاحظات إضافية
            $table->timestamps();
            
            // فهرس مركب لتجنب التكرار
            $table->unique(['employee_id', 'salary_date'], 'unique_employee_salary_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_deliveries');
    }
};
