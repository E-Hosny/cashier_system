<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
        });

        $labels = [
            'admin' => 'مدير',
            'cashier' => 'كاشير',
            'super admin' => 'سوبر أدمن',
            'barista' => 'باريستا',
            'hr' => 'مسؤول الموظفين',
        ];

        foreach ($labels as $name => $displayName) {
            DB::table('roles')->where('name', $name)->update([
                'display_name' => $displayName,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description']);
        });
    }
};
