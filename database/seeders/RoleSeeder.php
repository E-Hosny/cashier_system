<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // إنشاء الأدوار (أو الحصول عليها إذا كانت موجودة)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);

        // إنشاء الصلاحيات (أو الحصول عليها إذا كانت موجودة)
        Permission::firstOrCreate(['name' => 'view sales reports']);
        Permission::firstOrCreate(['name' => 'manage products']);
        Permission::firstOrCreate(['name' => 'use cashier']);
        Permission::firstOrCreate(['name' => 'manage users']);
        Permission::firstOrCreate(['name' => 'manage employee attendance']);

        // منح الصلاحيات للأدوار
        $adminRole->givePermissionTo(['view sales reports', 'manage products', 'use cashier', 'manage users', 'manage employee attendance']);
        $cashierRole->givePermissionTo(['use cashier', 'manage employee attendance']);
    }
}

