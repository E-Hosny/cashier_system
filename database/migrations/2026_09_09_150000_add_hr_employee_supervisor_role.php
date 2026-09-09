<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::firstOrCreate([
            'name' => 'hr',
            'guard_name' => 'web',
        ]);

        $permission = Permission::firstOrCreate([
            'name' => 'manage employee attendance',
            'guard_name' => 'web',
        ]);

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $role = Role::where('name', 'hr')->where('guard_name', 'web')->first();
        $role?->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
