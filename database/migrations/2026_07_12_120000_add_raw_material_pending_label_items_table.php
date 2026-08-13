<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('raw_material_pending_label_items')) {
            Schema::create('raw_material_pending_label_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raw_material_pending_label_id');
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('piece_count', 12, 4);
                $table->decimal('consume_amount', 14, 4);
                $table->unsignedBigInteger('source_label_id')->nullable();
                $table->timestamps();

                $table->foreign('raw_material_pending_label_id', 'rm_label_items_label_fk')
                    ->references('id')
                    ->on('raw_material_pending_labels')
                    ->cascadeOnDelete();
                $table->foreign('source_label_id', 'rm_label_items_source_fk')
                    ->references('id')
                    ->on('raw_material_pending_labels')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('raw_material_pending_labels', 'product_id')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY product_id BIGINT UNSIGNED NULL');
        }
        if (Schema::hasColumn('raw_material_pending_labels', 'piece_count')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY piece_count DECIMAL(12,4) NULL');
        }
        if (Schema::hasColumn('raw_material_pending_labels', 'consume_amount')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY consume_amount DECIMAL(14,4) NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_material_pending_label_items');

        if (Schema::hasColumn('raw_material_pending_labels', 'product_id')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY product_id BIGINT UNSIGNED NOT NULL');
        }
        if (Schema::hasColumn('raw_material_pending_labels', 'piece_count')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY piece_count DECIMAL(12,4) NOT NULL');
        }
        if (Schema::hasColumn('raw_material_pending_labels', 'consume_amount')) {
            DB::statement('ALTER TABLE raw_material_pending_labels MODIFY consume_amount DECIMAL(14,4) NOT NULL');
        }
    }
};
