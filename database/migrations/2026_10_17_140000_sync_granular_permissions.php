<?php

use App\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;

/**
 * ترحيل آمن: يضيف الصلاحيات التفصيلية ويمنحها للأدوار الحالية
 * دون حذف أي تعيينات موجودة (givePermissionTo فقط).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionSyncService::class)->sync();
    }

    public function down(): void
    {
        // لا نحذف الصلاحيات عند التراجع حتى لا نكسر تعيينات الإنتاج.
    }
};
