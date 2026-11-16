<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (! Schema::hasColumn('tenants', 'closing_shortages_enabled')) {
                    $table->boolean('closing_shortages_enabled')->default(false)->after('closing_fridge_show_in_sales_report');
                }
                if (! Schema::hasColumn('tenants', 'closing_shortages_types')) {
                    $table->json('closing_shortages_types')->nullable()->after('closing_shortages_enabled');
                }
            });
        }

        if (Schema::hasTable('closing_photo_report_submissions')
            && ! Schema::hasColumn('closing_photo_report_submissions', 'shortages_completed_at')) {
            Schema::table('closing_photo_report_submissions', function (Blueprint $table) {
                $table->timestamp('shortages_completed_at')->nullable()->after('fridge_counted_at');
            });
        }

        if (! Schema::hasTable('closing_shortage_hidden_materials')) {
            Schema::create('closing_shortage_hidden_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['tenant_id', 'product_id'], 'closing_shortage_hidden_unique');
            });
        }

        if (! Schema::hasTable('closing_shortage_custom_items')) {
            Schema::create('closing_shortage_custom_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('title');
                $table->string('category_label')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('closing_photo_report_shortage_answers')) {
            Schema::create('closing_photo_report_shortage_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')
                    ->constrained('closing_photo_report_submissions')
                    ->cascadeOnDelete();
                $table->string('source', 20); // raw | custom
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('custom_item_id')->nullable();
                $table->string('title_snapshot');
                $table->string('category_name')->nullable();
                $table->string('status', 20); // needed | not_needed
                $table->timestamps();

                $table->index(['submission_id', 'status'], 'cpr_shortage_status_idx');
                $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
                $table->foreign('custom_item_id', 'cpr_shortage_custom_fk')
                    ->references('id')
                    ->on('closing_shortage_custom_items')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_photo_report_shortage_answers');
        Schema::dropIfExists('closing_shortage_custom_items');
        Schema::dropIfExists('closing_shortage_hidden_materials');

        if (Schema::hasTable('closing_photo_report_submissions')
            && Schema::hasColumn('closing_photo_report_submissions', 'shortages_completed_at')) {
            Schema::table('closing_photo_report_submissions', function (Blueprint $table) {
                $table->dropColumn('shortages_completed_at');
            });
        }

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (Schema::hasColumn('tenants', 'closing_shortages_types')) {
                    $table->dropColumn('closing_shortages_types');
                }
                if (Schema::hasColumn('tenants', 'closing_shortages_enabled')) {
                    $table->dropColumn('closing_shortages_enabled');
                }
            });
        }
    }
};
