<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 64)->nullable()->after('name');
            }
        });

        if (Schema::hasColumn('products', 'tenant_id') && Schema::hasColumn('products', 'barcode')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->index(['tenant_id', 'barcode'], 'products_tenant_id_barcode_index');
                });
            } catch (\Throwable $e) {
                // index may already exist
            }
        }

        if (! Schema::hasTable('raw_material_pending_labels')) {
            Schema::create('raw_material_pending_labels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('label_code', 64)->unique();
                $table->decimal('piece_count', 12, 4);
                $table->decimal('consume_amount', 14, 4);
                $table->string('status', 20)->default('pending');
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
            });
        }

        $this->backfillProductBarcodes();
    }

    private function backfillProductBarcodes(): void
    {
        if (! Schema::hasColumn('products', 'barcode')) {
            return;
        }

        $ids = DB::table('products')
            ->where('type', 'raw')
            ->whereNull('barcode')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('products')->where('id', $id)->update([
                'barcode' => 'RM-'.strtoupper(Str::ulid()),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_pending_labels');

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'barcode')) {
                $table->dropIndex('products_tenant_id_barcode_index');
                $table->dropColumn('barcode');
            }
        });
    }
};
