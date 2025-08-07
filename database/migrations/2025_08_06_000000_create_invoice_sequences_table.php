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
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('date_code', 6)->comment('كود التاريخ بصيغة YYMMDD');
            $table->integer('current_sequence')->default(0)->comment('آخر رقم تسلسلي تم استخدامه');
            $table->timestamps();
            
            // فهرس فريد لكود التاريخ
            $table->unique('date_code');
            
            // فهرس للبحث السريع
            $table->index(['date_code', 'current_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
}; 