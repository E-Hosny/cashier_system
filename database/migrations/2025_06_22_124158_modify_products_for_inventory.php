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
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['raw', 'finished'])->default('finished')->after('name');
            $table->string('unit')->nullable()->after('type'); // e.g., kg, piece, liter
            $table->decimal('stock', 8, 2)->default(0)->after('unit');
            $table->decimal('stock_alert_threshold', 8, 2)->nullable()->after('stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['type', 'unit', 'stock', 'stock_alert_threshold']);
        });
    }
};
