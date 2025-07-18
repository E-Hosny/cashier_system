# أوامر السيرفر لحل مشكلة الصلاحيات

## المشكلة
الكاشير يحصل على خطأ 403 عند محاولة الوصول لصفحة الموظفين على السيرفر.

## الحل

### 1. مسح جميع الـ Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 2. إعادة تشغيل Seeder
```bash
php artisan db:seed --class=RoleSeeder
```

### 3. التحقق من الصلاحيات
```bash
php artisan tinker
```

ثم في Tinker:
```php
// التحقق من وجود الصلاحية
\Spatie\Permission\Models\Permission::where('name', 'manage employee attendance')->first();

// التحقق من الأدوار
\Spatie\Permission\Models\Role::with('permissions')->get()->each(function($role) {
    echo $role->name . ': ' . $role->permissions->pluck('name')->implode(', ') . PHP_EOL;
});

// التحقق من المستخدم الحالي
$user = \App\Models\User::find(1); // أو معرف المستخدم الكاشير
echo 'User roles: ' . $user->roles->pluck('name')->implode(', ') . PHP_EOL;
echo 'Can manage attendance: ' . ($user->can('manage employee attendance') ? 'Yes' : 'No') . PHP_EOL;
```

### 4. إعادة تعيين الأدوار للمستخدمين (إذا لزم الأمر)
```bash
php artisan tinker
```

```php
// إعادة تعيين دور الكاشير
$user = \App\Models\User::where('email', 'cashier@example.com')->first(); // استبدل بالبريد الإلكتروني الصحيح
$user->assignRole('cashier');

// أو إعادة تعيين جميع الأدوار
$users = \App\Models\User::all();
foreach($users as $user) {
    if($user->email === 'admin@example.com') {
        $user->assignRole('admin');
    } elseif($user->email === 'cashier@example.com') {
        $user->assignRole('cashier');
    }
}
```

### 5. التحقق من Middleware
```bash
php artisan route:list | grep employees
```

### 6. إعادة تشغيل الخادم (إذا كان ضرورياً)
```bash
# إذا كنت تستخدم Apache
sudo service apache2 restart

# إذا كنت تستخدم Nginx
sudo service nginx restart

# إذا كنت تستخدم PHP-FPM
sudo service php8.1-fpm restart
```

## إذا لم تحل المشكلة

### 1. التحقق من ملف .env
تأكد من أن إعدادات قاعدة البيانات صحيحة:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. التحقق من الصلاحيات في قاعدة البيانات
```sql
-- التحقق من وجود الصلاحية
SELECT * FROM permissions WHERE name = 'manage employee attendance';

-- التحقق من الأدوار
SELECT r.name as role_name, p.name as permission_name 
FROM roles r 
JOIN role_has_permissions rhp ON r.id = rhp.role_id 
JOIN permissions p ON rhp.permission_id = p.id 
WHERE p.name = 'manage employee attendance';

-- التحقق من المستخدمين وأدوارهم
SELECT u.name, u.email, r.name as role_name 
FROM users u 
JOIN model_has_roles mhr ON u.id = mhr.model_id 
JOIN roles r ON mhr.role_id = r.id;
```

### 3. إعادة إنشاء الصلاحيات يدوياً
```bash
php artisan tinker
```

```php
// حذف الصلاحية وإعادة إنشاؤها
\Spatie\Permission\Models\Permission::where('name', 'manage employee attendance')->delete();

// إنشاء الصلاحية من جديد
$permission = \Spatie\Permission\Models\Permission::create(['name' => 'manage employee attendance']);

// منح الصلاحية للأدوار
$adminRole = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
$cashierRole = \Spatie\Permission\Models\Role::where('name', 'cashier')->first();

$adminRole->givePermissionTo($permission);
$cashierRole->givePermissionTo($permission);
```

## ملاحظات مهمة

1. **تأكد من أن المستخدم لديه الدور الصحيح**
2. **تأكد من أن الصلاحية موجودة في قاعدة البيانات**
3. **تأكد من أن Middleware مسجل بشكل صحيح**
4. **تأكد من أن Cache تم مسحه**

## للتحقق من الحل

بعد تنفيذ الأوامر، جرب:
1. تسجيل دخول ككاشير
2. الذهاب للـ Dashboard
3. الضغط على كارت الموظفين
4. يجب أن تعمل الصفحة بدون خطأ 403 