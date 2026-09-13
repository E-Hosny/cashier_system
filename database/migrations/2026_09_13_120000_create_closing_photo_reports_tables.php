<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'closing_photo_reports_enabled')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->boolean('closing_photo_reports_enabled')->default(false)->after('logo_path');
                $table->time('closing_evening_starts_at')->default('17:00:00')->after('closing_photo_reports_enabled');
                $table->time('closing_evening_ends_at')->default('23:59:59')->after('closing_evening_starts_at');
                $table->time('closing_dawn_starts_at')->default('00:00:00')->after('closing_evening_ends_at');
                $table->time('closing_dawn_ends_at')->default('06:59:59')->after('closing_dawn_starts_at');
            });
        }

        if (! Schema::hasTable('closing_photo_report_items')) {
            Schema::create('closing_photo_report_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('closing_type', 16); // evening | dawn
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_required')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'closing_type', 'is_active'], 'cpr_items_tenant_type_active_idx');
            });
        } else {
            try {
                Schema::table('closing_photo_report_items', function (Blueprint $table) {
                    $table->index(['tenant_id', 'closing_type', 'is_active'], 'cpr_items_tenant_type_active_idx');
                });
            } catch (\Throwable) {
            }
        }

        if (! Schema::hasTable('closing_photo_report_submissions')) {
            Schema::create('closing_photo_report_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->string('closing_type', 16);
                $table->date('business_date');
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['branch_id', 'closing_type', 'business_date'],
                    'cpr_submissions_unique'
                );
                $table->index(['tenant_id', 'business_date'], 'cpr_submissions_tenant_date_idx');
            });
        }

        if (! Schema::hasTable('closing_photo_report_photos')) {
            Schema::create('closing_photo_report_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('submission_id')
                    ->constrained('closing_photo_report_submissions')
                    ->cascadeOnDelete();
                $table->foreignId('item_id')
                    ->constrained('closing_photo_report_items')
                    ->cascadeOnDelete();
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['submission_id', 'item_id'], 'cpr_photo_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_photo_report_photos');
        Schema::dropIfExists('closing_photo_report_submissions');
        Schema::dropIfExists('closing_photo_report_items');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'closing_photo_reports_enabled',
                'closing_evening_starts_at',
                'closing_evening_ends_at',
                'closing_dawn_starts_at',
                'closing_dawn_ends_at',
            ]);
        });
    }
};
