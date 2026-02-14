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
        Schema::create('display_screen_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('interval_seconds')->default(3);
            $table->timestamps();
        });

        Schema::create('display_screen_slides', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('display_screen_slides');
        Schema::dropIfExists('display_screen_config');
    }
};
