<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreignId('note_by_user_id')->nullable()->after('note')->constrained('users')->nullOnDelete();
            $table->timestamp('note_updated_at')->nullable()->after('note_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('note_by_user_id');
            $table->dropColumn('note_updated_at');
        });
    }
};
