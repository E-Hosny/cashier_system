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
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('shift_type', ['morning', 'evening'])->comment('نوع الوردية: صباحي أو مسائي');
            $table->timestamp('start_time')->comment('وقت بداية الوردية');
            $table->timestamp('end_time')->nullable()->comment('وقت انتهاء الوردية');
            $table->decimal('total_sales', 12, 2)->default(0)->comment('إجمالي المبيعات في الوردية');
            $table->decimal('cash_amount', 12, 2)->default(0)->comment('المبلغ النقدي الموجود في الصندوق');
            $table->decimal('expected_amount', 12, 2)->default(0)->comment('المبلغ المتوقع حسب النظام');
            $table->decimal('difference', 12, 2)->default(0)->comment('الفرق بين النقدي والمتوقع');
            $table->text('notes')->nullable()->comment('ملاحظات إضافية');
            $table->enum('status', ['active', 'closed', 'handed_over'])->default('active')->comment('حالة الوردية');
            $table->timestamps();
            
            // فهارس لتحسين الأداء
            $table->index(['user_id', 'status']);
            $table->index(['start_time', 'end_time']);
            $table->index('shift_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
}; 