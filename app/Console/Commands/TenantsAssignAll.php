<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantsAssignAll extends Command
{
    protected $signature = 'tenants:assign-all
                            {tenant : معرّف الـ tenant (ID أو slug)}
                            {--force : تعيين كل الصفوف لهذا الـ tenant (بما فيها التي لديها tenant_id بالفعل)}';

    protected $description = 'تعيين كل الجداول التي تحتوي tenant_id إلى الـ tenant المحدد (الافتراضي: الصفوف التي tenant_id فيها null فقط)';

    public function handle(): int
    {
        $tenantInput = $this->argument('tenant');
        $tenant = is_numeric($tenantInput)
            ? Tenant::find($tenantInput)
            : Tenant::where('slug', $tenantInput)->first();

        if (!$tenant) {
            $this->error('لم يتم العثور على الـ tenant المحدد.');
            return self::FAILURE;
        }

        $tenantId = $tenant->id;
        $force = $this->option('force');

        $tables = $this->getTenantTables();

        $this->info("الـ tenant المستهدف: {$tenant->name} (ID: {$tenantId})");
        $this->newLine();

        foreach ($tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $updated = $force
                ? DB::table($table)->update(['tenant_id' => $tenantId])
                : DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);

            if ($updated > 0) {
                $this->line("  [{$table}] تم تحديث {$updated} صفاً.");
            }
        }

        $this->newLine();
        $this->info('تم تنفيذ الأمر بنجاح.');

        return self::SUCCESS;
    }

    private function getTenantTables(): array
    {
        return [
            'users',
            'products',
            'orders',
            'order_items',
            'purchases',
            'suppliers',
            'employee_discounts',
            'categories',
            'ingredients',
            'stock_movements',
            'invoice_sequences',
            'expenses',
            'employees',
            'employee_attendances',
            'feedback',
            'salary_deliveries',
            'cashier_shifts',
            'display_screen_config',
            'display_screen_slides',
        ];
    }
}
