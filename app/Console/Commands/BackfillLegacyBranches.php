<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class BackfillLegacyBranches extends Command
{
    protected $signature = 'branches:backfill-legacy
                            {--tenant= : معرّف المستأجر (ID أو slug) — الافتراضي: كل المستأجرين}
                            {--branch= : معرّف فرع محدد (بدلاً من الفرع الأول/الرئيسي)}
                            {--sync-stock : استبدال مخزون الفرع بقيم المخزون المركزي للمواد الخام}
                            {--dry-run : عرض ما سيُنفَّذ دون تعديل}
                            {--force : تنفيذ دون تأكيد}';

    protected $description = 'ربط البيانات القديمة (قبل الفروع) بفرع واحد وتهيئة مخزون المواد الخام للفرع';

    /** @var list<string> */
    private array $branchScopedTables = [
        'orders',
        'employees',
        'attendance_groups',
        'cashier_shifts',
        'expenses',
    ];

    public function handle(): int
    {
        if (! Schema::hasTable('branches')) {
            $this->error('جدول branches غير موجود. شغّل php artisan migrate أولاً.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $syncStock = (bool) $this->option('sync-stock');

        if ($dryRun) {
            $this->warn('وضع التجربة (dry-run) — لن يُحفظ أي تعديل.');
        }

        $tenants = $this->resolveTenants();
        if ($tenants->isEmpty()) {
            $this->error('لم يُعثر على مستأجر.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('تأكيد: سيتم ربط السجلات ذات branch_id الفارغ بالفرع المحدد. هل تتابع؟', true)) {
            $this->info('تم الإلغاء.');

            return self::SUCCESS;
        }

        $this->info('بدء ربط البيانات القديمة بالفروع…');
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $dryRun, $syncStock);
        }

        $this->newLine();
        $this->info($dryRun ? 'انتهى التحقق (dry-run).' : 'تم تنفيذ الربط بنجاح.');

        return self::SUCCESS;
    }

    private function resolveTenants()
    {
        $input = $this->option('tenant');
        if ($input === null || $input === '') {
            return Tenant::query()->orderBy('id')->get();
        }

        $tenant = is_numeric($input)
            ? Tenant::find((int) $input)
            : Tenant::where('slug', $input)->first();

        return $tenant ? collect([$tenant]) : collect();
    }

    private function processTenant(Tenant $tenant, bool $dryRun, bool $syncStock): void
    {
        $this->info("═══ المستأجر: {$tenant->name} (ID: {$tenant->id}) ═══");

        $branch = $this->resolveBranch($tenant);
        if (! $branch) {
            $this->warn('  تخطّي: لا يوجد فرع.');

            return;
        }

        $branchId = $branch->id;
        $this->line("  الفرع المستخدم: {$branch->name} (ID: {$branchId})");

        foreach ($this->branchScopedTables as $table) {
            $this->backfillBranchIdColumn($table, $tenant->id, $branchId, $dryRun);
        }

        $this->backfillUsers($tenant->id, $branchId, $dryRun);
        $this->backfillInvoiceSequences($tenant->id, $branchId, $dryRun);
        $this->backfillStockMovementsFromOrders($tenant->id, $branchId, $dryRun);
        $this->backfillPendingLabels($tenant->id, $branchId, $dryRun);
        $this->backfillBranchRawMaterialStock($tenant->id, $branchId, $dryRun, $syncStock);

        $this->newLine();
    }

    private function resolveBranch(Tenant $tenant): ?Branch
    {
        $branchOption = $this->option('branch');
        if ($branchOption !== null && $branchOption !== '') {
            return Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereKey((int) $branchOption)
                ->first();
        }

        $existing = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        if ($this->option('dry-run')) {
            $this->line('  [dry-run] سيُنشأ فرع «الفرع الرئيسي»');

            return null;
        }

        return Branch::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'الفرع الرئيسي',
            'slug' => 'main-'.$tenant->id,
            'is_active' => true,
        ]);
    }

    private function backfillBranchIdColumn(string $table, int $tenantId, int $branchId, bool $dryRun): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'branch_id')) {
            return;
        }

        $query = DB::table($table)->whereNull('branch_id');
        if (Schema::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }

        $this->line("  [{$table}] ربط {$count} سجل بـ branch_id={$branchId}");

        if (! $dryRun) {
            (clone $query)->update(['branch_id' => $branchId]);
        }
    }

    private function backfillUsers(int $tenantId, int $branchId, bool $dryRun): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'branch_id')) {
            return;
        }

        $superAdminIds = $this->superAdminUserIdsForTenant($tenantId);

        $query = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereNull('branch_id');

        if ($superAdminIds !== []) {
            $query->whereNotIn('id', $superAdminIds);
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }

        $this->line("  [users] ربط {$count} مستخدم (بدون سوبر أدمن) بـ branch_id={$branchId}");

        if (! $dryRun) {
            (clone $query)->update(['branch_id' => $branchId]);
        }
    }

    /**
     * @return list<int>
     */
    private function superAdminUserIdsForTenant(int $tenantId): array
    {
        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return [];
        }

        $roleId = DB::table('roles')->where('name', 'super admin')->value('id');
        if (! $roleId) {
            return [];
        }

        return DB::table('users')
            ->join('model_has_roles', function ($join) use ($roleId) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.role_id', $roleId)
                    ->where('model_has_roles.model_type', \App\Models\User::class);
            })
            ->where('users.tenant_id', $tenantId)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function backfillInvoiceSequences(int $tenantId, int $branchId, bool $dryRun): void
    {
        if (! Schema::hasTable('invoice_sequences') || ! Schema::hasColumn('invoice_sequences', 'branch_id')) {
            return;
        }

        $query = DB::table('invoice_sequences')->whereNull('branch_id');
        if (Schema::hasColumn('invoice_sequences', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $count = (clone $query)->count();
        if ($count > 0) {
            $this->line("  [invoice_sequences] ربط {$count} سجل");
            if (! $dryRun) {
                (clone $query)->update(['branch_id' => $branchId]);
            }
        }

        $orphan = DB::table('invoice_sequences')->whereNull('branch_id')->count();
        if ($orphan > 0 && ! $dryRun) {
            DB::table('invoice_sequences')->whereNull('branch_id')->delete();
            $this->warn("  [invoice_sequences] حذف {$orphan} سجل بدون فرع (تعارض مع القيد الفريد).");
        }
    }

    private function backfillStockMovementsFromOrders(int $tenantId, int $branchId, bool $dryRun): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasColumn('stock_movements', 'branch_id')) {
            return;
        }

        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'branch_id')) {
            return;
        }

        $countQuery = DB::table('stock_movements as sm')
            ->join('orders as o', 'sm.related_order_id', '=', 'o.id')
            ->whereNull('sm.branch_id')
            ->where('o.tenant_id', $tenantId)
            ->where('o.branch_id', $branchId);

        if (Schema::hasColumn('stock_movements', 'tenant_id')) {
            $countQuery->where('sm.tenant_id', $tenantId);
        }

        $count = $countQuery->count();
        if ($count === 0) {
            return;
        }

        $this->line("  [stock_movements] ربط {$count} حركة بفواتير الفرع");

        if ($dryRun) {
            return;
        }

        $updateSql = 'UPDATE stock_movements sm
            INNER JOIN orders o ON sm.related_order_id = o.id
            SET sm.branch_id = ?
            WHERE sm.branch_id IS NULL
            AND o.tenant_id = ?
            AND o.branch_id = ?';

        $bindings = [$branchId, $tenantId, $branchId];

        if (Schema::hasColumn('stock_movements', 'tenant_id')) {
            $updateSql .= ' AND sm.tenant_id = ?';
            $bindings[] = $tenantId;
        }

        DB::update($updateSql, $bindings);
    }

    private function backfillPendingLabels(int $tenantId, int $branchId, bool $dryRun): void
    {
        if (! Schema::hasTable('raw_material_pending_labels') || ! Schema::hasColumn('raw_material_pending_labels', 'branch_id')) {
            return;
        }

        $query = DB::table('raw_material_pending_labels')
            ->whereNull('branch_id')
            ->where('status', 'received');

        if (Schema::hasColumn('raw_material_pending_labels', 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }

        $count = (clone $query)->count();
        if ($count === 0) {
            return;
        }

        $this->line("  [raw_material_pending_labels] ربط {$count} ملصق مستلم");

        if (! $dryRun) {
            (clone $query)->update(['branch_id' => $branchId]);
        }
    }

    private function backfillBranchRawMaterialStock(int $tenantId, int $branchId, bool $dryRun, bool $syncStock): void
    {
        if (! Schema::hasTable('branch_raw_material_stocks')) {
            $this->warn('  جدول branch_raw_material_stocks غير موجود — شغّل migrate أولاً.');

            return;
        }

        $products = Product::withoutGlobalScopes()
            ->where('type', 'raw')
            ->where('tenant_id', $tenantId)
            ->get(['id', 'stock', 'stock_alert_threshold']);

        if ($products->isEmpty()) {
            return;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $existing = DB::table('branch_raw_material_stocks')
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->first();

            $centralStock = (float) $product->stock;

            if ($existing && ! $syncStock) {
                if ((float) $existing->stock > 0) {
                    $skipped++;

                    continue;
                }
                if ($centralStock <= 0) {
                    $skipped++;

                    continue;
                }
            }

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }

            if ($dryRun) {
                continue;
            }

            DB::table('branch_raw_material_stocks')->updateOrInsert(
                [
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                ],
                [
                    'tenant_id' => $tenantId,
                    'stock' => $centralStock,
                    'stock_alert_threshold' => $product->stock_alert_threshold,
                    'updated_at' => now(),
                    'created_at' => $existing->created_at ?? now(),
                ]
            );
        }

        $this->line("  [branch_raw_material_stocks] إنشاء: {$created} | تحديث: {$updated} | تخطّي: {$skipped}");
        if ($syncStock && ! $dryRun) {
            $this->comment('  (--sync-stock: تمت مزامنة المخزون من المركزي)');
        } elseif ($skipped > 0 && ! $syncStock) {
            $this->comment('  (صفوف موجودة بمخزون > 0 لم تُمس — استخدم --sync-stock للاستبدال)');
        }
    }
}
