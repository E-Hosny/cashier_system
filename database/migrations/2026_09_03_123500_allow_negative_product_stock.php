<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Drop UNSIGNED so central stock can go below zero on branch pulls.
        DB::statement('ALTER TABLE products MODIFY stock DECIMAL(14, 4) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE products MODIFY stock DECIMAL(8, 2) UNSIGNED NOT NULL DEFAULT 0');
    }
};
