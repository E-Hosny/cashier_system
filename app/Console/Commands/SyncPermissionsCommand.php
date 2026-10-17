<?php

namespace App\Console\Commands;

use App\Services\PermissionSyncService;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'مزامنة كتالوج الصلاحيات التفصيلي مع قاعدة البيانات (إضافة فقط — آمن للإنتاج)';

    public function handle(PermissionSyncService $sync): int
    {
        $this->info('جاري مزامنة الصلاحيات...');

        $result = $sync->sync();

        $this->table(
            ['مقياس', 'القيمة'],
            [
                ['صلاحيات جديدة', $result['created_permissions']],
                ['أدوار تم تحديثها', $result['roles_updated']],
                ['منح مُضافة', $result['grants_added']],
            ]
        );

        $this->info('تمت المزامنة بنجاح — لم يُحذف أي تعيين قائم.');

        return self::SUCCESS;
    }
}
