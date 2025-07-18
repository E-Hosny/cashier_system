# حل سريع لمشكلة الصلاحيات على السيرفر

## 🚨 المشكلة
الكاشير يحصل على خطأ 403 عند محاولة الوصول لصفحة الموظفين على السيرفر.

## ⚡ الحل السريع

### الطريقة الأولى: استخدام السكريبت
```bash
# على السيرفر، في مجلد المشروع
chmod +x quick_server_fix.sh
./quick_server_fix.sh
```

### الطريقة الثانية: الأوامر اليدوية
```bash
# 1. مسح Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 2. إعادة تشغيل Seeder
php artisan db:seed --class=RoleSeeder

# 3. تشغيل فحص الصلاحيات
php check_permissions.php
```

### الطريقة الثالثة: Tinker
```bash
php artisan tinker
```

```php
// إنشاء الصلاحية إذا لم تكن موجودة
$permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'manage employee attendance']);

// منح الصلاحية للأدوار
$adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
$cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')->first();

$adminRole->givePermissionTo($permission);
$cashierRole->givePermissionTo($permission);

// التحقق من المستخدم الكاشير
$user = \App\Models\User::where('email', 'cashier@example.com')->first(); // استبدل بالبريد الصحيح
$user->assignRole('cashier');

echo "تم الإصلاح!";
```

## 🔍 للتحقق من الحل

```bash
php artisan tinker
```

```php
// التحقق من الصلاحيات
\Spatie\Permission\Models\Permission::where('name', 'manage employee attendance')->first();

// التحقق من الأدوار
\Spatie\Permission\Models\Role::with('permissions')->get()->each(function($role) {
    echo $role->name . ': ' . $role->permissions->pluck('name')->implode(', ') . PHP_EOL;
});

// التحقق من المستخدم
$user = \App\Models\User::where('email', 'cashier@example.com')->first();
echo 'User roles: ' . $user->roles->pluck('name')->implode(', ') . PHP_EOL;
echo 'Can manage attendance: ' . ($user->can('manage employee attendance') ? 'Yes' : 'No') . PHP_EOL;
```

## 🎯 النتيجة المتوقعة

بعد تنفيذ الحل:
- ✅ الكاشير يمكنه الوصول لصفحة الموظفين
- ✅ لا يظهر خطأ 403
- ✅ يمكن تسجيل الحضور والانصراف

## 📞 إذا لم تحل المشكلة

1. **تحقق من قاعدة البيانات**: تأكد من أن المستخدم لديه الدور الصحيح
2. **تحقق من Cache**: تأكد من مسح جميع الـ Cache
3. **تحقق من Middleware**: تأكد من تسجيل middleware بشكل صحيح
4. **تحقق من الملفات**: تأكد من رفع جميع الملفات المحدثة

## 🚀 أوامر إضافية مفيدة

```bash
# إعادة تشغيل الخادم
sudo service apache2 restart
# أو
sudo service nginx restart

# مسح جميع الـ Cache
php artisan optimize:clear

# إعادة إنشاء autoload
composer dump-autoload
``` 