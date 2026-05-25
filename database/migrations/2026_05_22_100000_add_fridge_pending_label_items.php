<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fridge_pending_label_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fridge_pending_label_id')->constrained('fridge_pending_labels')->cascadeOnDelete();
            $table->foreignId('fridge_product_config_id')->constrained('fridge_product_configs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 64)->default('');
            $table->decimal('unit_count', 14, 4);
            $table->timestamps();
        });

        Schema::table('fridge_pending_labels', function (Blueprint $table) {
            $table->foreignId('fridge_product_config_id')->nullable()->change();
            $table->foreignId('product_id')->nullable()->change();
            $table->decimal('unit_count', 14, 4)->nullable()->change();
        });

        foreach (DB::table('fridge_pending_labels')->whereNotNull('fridge_product_config_id')->get() as $label) {
            if (DB::table('fridge_pending_label_items')->where('fridge_pending_label_id', $label->id)->exists()) {
                continue;
            }
            DB::table('fridge_pending_label_items')->insert([
                'fridge_pending_label_id' => $label->id,
                'fridge_product_config_id' => $label->fridge_product_config_id,
                'product_id' => $label->product_id,
                'size' => $label->size ?? '',
                'unit_count' => $label->unit_count,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fridge_pending_label_items');
    }
};
