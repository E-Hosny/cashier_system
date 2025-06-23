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
        // Create suppliers table if it doesn't exist
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('contact_person')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->foreignId('tenant_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // Modify the purchases table
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'product_name') && !Schema::hasColumn('purchases', 'description')) {
                $table->renameColumn('product_name', 'description');
            }
            if (!Schema::hasColumn('purchases', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->onDelete('set null')->after('id');
            }
            if (!Schema::hasColumn('purchases', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->after('quantity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }
            if (Schema::hasColumn('purchases', 'total_amount')) {
                $table->dropColumn('total_amount');
            }
            if (Schema::hasColumn('purchases', 'description') && !Schema::hasColumn('purchases', 'product_name')) {
                $table->renameColumn('description', 'product_name');
            }
        });

        Schema::dropIfExists('suppliers');
    }
};
