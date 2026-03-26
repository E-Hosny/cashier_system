<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product = فئات المنتجات النهائية، raw = فئات المواد الخام فقط.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('scope', 16)->default('product')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
