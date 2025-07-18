#!/bin/bash

echo "🔧 بدء إصلاح مشكلة الصلاحيات على السيرفر..."

# مسح جميع الـ Cache
echo "📦 مسح Cache..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# إعادة تشغيل Seeder
echo "🌱 إعادة تشغيل Seeder..."
php artisan db:seed --class=RoleSeeder

# التحقق من الصلاحيات
echo "🔍 التحقق من الصلاحيات..."
php artisan tinker --execute="
echo '=== الصلاحيات الموجودة ===';
\Spatie\Permission\Models\Permission::all()->each(function(\$p) { echo \$p->name . PHP_EOL; });

echo '=== الأدوار والصلاحيات ===';
\Spatie\Permission\Models\Role::with('permissions')->get()->each(function(\$role) {
    echo \$role->name . ': ' . \$role->permissions->pluck('name')->implode(', ') . PHP_EOL;
});

echo '=== المستخدمين وأدوارهم ===';
\App\Models\User::with('roles')->get()->each(function(\$user) {
    echo \$user->name . ' (' . \$user->email . '): ' . \$user->roles->pluck('name')->implode(', ') . PHP_EOL;
});
"

echo "✅ تم الانتهاء من الإصلاح!"
echo "🎯 جرب الآن تسجيل دخول ككاشير والوصول لصفحة الموظفين" 