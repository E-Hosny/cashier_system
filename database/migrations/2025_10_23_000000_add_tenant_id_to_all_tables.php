<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة tenant_id للجداول التي لا تحتوي عليه
        $tables = [
            'categories',
            'employees',
            'employee_attendances',
            'expenses',
            'feedback',
            'invoice_sequences',
            'salary_deliveries',
            'stock_movements',
            'cashier_shifts',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                    $table->index('tenant_id');
                });
            }
        }

        // تحديث البيانات الموجودة بناءً على العلاقات
        $this->updateExistingData();
    }

    /**
     * تحديث البيانات الموجودة
     */
    private function updateExistingData(): void
    {
        // 1. تحديث cashier_shifts من user_id
        if (Schema::hasTable('cashier_shifts') && Schema::hasColumn('cashier_shifts', 'tenant_id')) {
            DB::statement('
                UPDATE cashier_shifts cs
                INNER JOIN users u ON cs.user_id = u.id
                SET cs.tenant_id = u.tenant_id
                WHERE cs.tenant_id IS NULL
            ');
        }

        // 2. تحديث stock_movements من product_id
        if (Schema::hasTable('stock_movements') && Schema::hasColumn('stock_movements', 'tenant_id')) {
            DB::statement('
                UPDATE stock_movements sm
                INNER JOIN products p ON sm.product_id = p.id
                SET sm.tenant_id = p.tenant_id
                WHERE sm.tenant_id IS NULL AND p.tenant_id IS NOT NULL
            ');
        }

        // 3. تحديث employee_attendances من employee_id
        if (Schema::hasTable('employee_attendances') && Schema::hasColumn('employee_attendances', 'tenant_id')) {
            DB::statement('
                UPDATE employee_attendances ea
                INNER JOIN employees e ON ea.employee_id = e.id
                SET ea.tenant_id = e.tenant_id
                WHERE ea.tenant_id IS NULL AND e.tenant_id IS NOT NULL
            ');
        }

        // 4. تحديث salary_deliveries من employee_id
        if (Schema::hasTable('salary_deliveries') && Schema::hasColumn('salary_deliveries', 'tenant_id')) {
            DB::statement('
                UPDATE salary_deliveries sd
                INNER JOIN employees e ON sd.employee_id = e.id
                SET sd.tenant_id = e.tenant_id
                WHERE sd.tenant_id IS NULL AND e.tenant_id IS NOT NULL
            ');
        }

        // 5. للجداول التي ليس لها علاقة مباشرة، نحاول تحديثها من أول مستخدم له tenant_id
        $firstTenantId = DB::table('users')->whereNotNull('tenant_id')->value('tenant_id');
        
        if ($firstTenantId) {
            // تحديث categories
            if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'tenant_id')) {
                DB::table('categories')->whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
            }

            // تحديث employees
            if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'tenant_id')) {
                DB::table('employees')->whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
            }

            // تحديث expenses
            if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'tenant_id')) {
                DB::table('expenses')->whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
            }

            // تحديث feedback
            if (Schema::hasTable('feedback') && Schema::hasColumn('feedback', 'tenant_id')) {
                DB::table('feedback')->whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
            }

            // تحديث invoice_sequences
            if (Schema::hasTable('invoice_sequences') && Schema::hasColumn('invoice_sequences', 'tenant_id')) {
                DB::table('invoice_sequences')->whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'categories',
            'employees',
            'employee_attendances',
            'expenses',
            'feedback',
            'invoice_sequences',
            'salary_deliveries',
            'stock_movements',
            'cashier_shifts',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                    $table->dropIndex(['tenant_id']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};

