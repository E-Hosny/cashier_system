<?php

use App\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;

/**
 * يضيف صلاحية حد قبض الساعة ويمنحها للسوبر أدمن فقط (givePermissionTo).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionSyncService::class)->sync();
    }

    public function down(): void
    {
        // لا نحذف الصلاحية حتى لا نكسر تعيينات موجودة.
    }
};
