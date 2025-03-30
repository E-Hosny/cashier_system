<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // إنشاء الأدوار
        $adminRole = Role::create(['name' => 'admin']);
        $cashierRole = Role::create(['name' => 'cashier']);

        // إنشاء الصلاحيات
        Permission::create(['name' => 'view sales reports']);
        Permission::create(['name' => 'manage products']);
        Permission::create(['name' => 'use cashier']);

        // منح الصلاحيات للأدوار
        $adminRole->givePermissionTo(['view sales reports', 'manage products', 'use cashier']);
        $cashierRole->givePermissionTo(['use cashier']);
    }
}

