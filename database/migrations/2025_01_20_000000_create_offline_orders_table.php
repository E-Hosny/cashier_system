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
        Schema::create('offline_orders', function (Blueprint $table) {
            $table->id();
            $table->string('offline_id')->unique()->comment('معرف فريد للطلب في وضع عدم الاتصال');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->onDelete('set null');
            $table->decimal('total', 12, 2);
            $table->string('payment_method');
            $table->string('status')->default('pending_sync')->comment('pending_sync, synced, failed');
            $table->string('invoice_number')->nullable();
            $table->json('items')->comment('تفاصيل عناصر الطلب');
            $table->json('stock_movements')->nullable()->comment('حركات المخزون المرتبطة');
            $table->text('sync_error')->nullable()->comment('رسالة الخطأ في حالة فشل المزامنة');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('sync_attempted_at')->nullable();
            
            // فهارس لتحسين الأداء
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('offline_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_orders');
    }
}; 