<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('closing_photo_report_submissions')
            && ! Schema::hasColumn('closing_photo_report_submissions', 'fridge_counted_at')) {
            Schema::table('closing_photo_report_submissions', function (Blueprint $table) {
                $table->timestamp('fridge_counted_at')->nullable()->after('completed_at');
            });
        }

        // قد تفشل المحاولة السابقة بعد إنشاء الجدول جزئياً
        Schema::dropIfExists('closing_photo_report_fridge_counts');

        Schema::create('closing_photo_report_fridge_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('closing_photo_report_submissions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('fridge_product_config_id');
            $table->foreign('fridge_product_config_id', 'cpr_fridge_cfg_fk')
                ->references('id')
                ->on('fridge_product_configs')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 64)->default('');
            $table->string('product_name')->nullable();
            $table->decimal('system_qty', 14, 4)->default(0);
            $table->decimal('actual_qty', 14, 4)->default(0);
            $table->decimal('diff_qty', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['submission_id', 'fridge_product_config_id'], 'cpr_fridge_count_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_photo_report_fridge_counts');

        if (Schema::hasTable('closing_photo_report_submissions')
            && Schema::hasColumn('closing_photo_report_submissions', 'fridge_counted_at')) {
            Schema::table('closing_photo_report_submissions', function (Blueprint $table) {
                $table->dropColumn('fridge_counted_at');
            });
        }
    }
};
