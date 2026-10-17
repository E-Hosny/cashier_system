<?php

namespace Database\Seeders;

use App\Services\PermissionSyncService;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // إنشاء الأدوار الأساسية إن لم تكن موجودة
        foreach (array_keys(PermissionCatalog::systemRoleDefaults()) as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['display_name' => match ($roleName) {
                    'admin' => 'مدير',
                    'cashier' => 'كاشير',
                    'super admin' => 'سوبر أدمن',
                    'barista' => 'باريستا',
                    'hr' => 'مسؤول الموظفين',
                    default => $roleName,
                }]
            );
        }

        app(PermissionSyncService::class)->sync();
    }
}
