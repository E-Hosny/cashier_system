<?php

require_once 'vendor/autoload.php';

// تحميل Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 فحص الصلاحيات والأدوار...\n\n";

// التحقق من وجود الصلاحية
$permission = \Spatie\Permission\Models\Permission::where('name', 'manage employee attendance')->first();
if ($permission) {
    echo "✅ الصلاحية 'manage employee attendance' موجودة\n";
} else {
    echo "❌ الصلاحية 'manage employee attendance' غير موجودة - سيتم إنشاؤها\n";
    $permission = \Spatie\Permission\Models\Permission::create(['name' => 'manage employee attendance']);
}

// التحقق من الأدوار
echo "\n📋 الأدوار والصلاحيات:\n";
$roles = \Spatie\Permission\Models\Role::with('permissions')->get();
foreach ($roles as $role) {
    echo "- {$role->name}: " . $role->permissions->pluck('name')->implode(', ') . "\n";
}

// التحقق من المستخدمين
echo "\n👥 المستخدمين وأدوارهم:\n";
$users = \App\Models\User::with('roles')->get();
foreach ($users as $user) {
    $roles = $user->roles->pluck('name')->implode(', ');
    $canManageAttendance = $user->can('manage employee attendance') ? '✅' : '❌';
    echo "- {$user->name} ({$user->email}): {$roles} - يمكن إدارة الحضور: {$canManageAttendance}\n";
}

// إصلاح الصلاحيات إذا لزم الأمر
echo "\n🔧 إصلاح الصلاحيات...\n";

$adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
$cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')->first();

if ($adminRole && !$adminRole->hasPermissionTo('manage employee attendance')) {
    $adminRole->givePermissionTo('manage employee attendance');
    echo "✅ تم منح الصلاحية لدور admin\n";
}

if ($cashierRole && !$cashierRole->hasPermissionTo('manage employee attendance')) {
    $cashierRole->givePermissionTo('manage employee attendance');
    echo "✅ تم منح الصلاحية لدور cashier\n";
}

// إعادة تعيين الأدوار للمستخدمين إذا لم يكن لديهم أدوار
foreach ($users as $user) {
    if ($user->roles->isEmpty()) {
        if (str_contains(strtolower($user->email), 'admin')) {
            $user->assignRole('admin');
            echo "✅ تم تعيين دور admin للمستخدم {$user->name}\n";
        } else {
            $user->assignRole('cashier');
            echo "✅ تم تعيين دور cashier للمستخدم {$user->name}\n";
        }
    }
}

echo "\n✅ تم الانتهاء من الفحص والإصلاح!\n";
echo "🎯 جرب الآن تسجيل دخول ككاشير والوصول لصفحة الموظفين\n"; 