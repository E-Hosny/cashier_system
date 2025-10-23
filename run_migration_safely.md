# دليل تشغيل Migration بأمان

## ⚠️ مهم جداً: اقرأ قبل التنفيذ

هذا الدليل يشرح كيفية تطبيق نظام Multi-Tenancy بأمان دون فقدان أي بيانات.

## الخطوة 1: النسخ الاحتياطي

### قبل أي شيء، قم بعمل نسخة احتياطية من قاعدة البيانات:

```bash
# من خلال phpMyAdmin
# 1. افتح phpMyAdmin
# 2. اختر قاعدة البيانات
# 3. اضغط على "Export"
# 4. اختر "Quick" أو "Custom"
# 5. احفظ الملف

# أو من خلال Command Line
mysqldump -u root -p cashier_system > backup_before_tenant_$(date +%Y%m%d_%H%M%S).sql
```

## الخطوة 2: التحقق من البيئة

```bash
# تأكد من أنك في المجلد الصحيح
cd C:\xampp\htdocs\cashier_system

# تحقق من الاتصال بقاعدة البيانات
php artisan tinker
DB::connection()->getPdo();
exit
```

## الخطوة 3: فحص الملفات الجديدة

تأكد من وجود الملفات التالية:

```
✅ database/migrations/2025_10_23_000000_add_tenant_id_to_all_tables.php
✅ app/Http/Middleware/EnsureTenantScope.php
✅ TENANT_SYSTEM_DOCUMENTATION.md
✅ TESTING_TENANT_SYSTEM.md
```

## الخطوة 4: فحص Migration قبل التشغيل

```bash
php artisan tinker

# فحص الجداول الحالية
$tables = ['categories', 'employees', 'expenses', 'feedback', 'invoice_sequences', 
           'salary_deliveries', 'stock_movements', 'cashier_shifts'];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $hasColumn = Schema::hasColumn($table, 'tenant_id');
        echo "$table: " . ($hasColumn ? 'Already has tenant_id' : 'Will add tenant_id') . "\n";
    } else {
        echo "$table: Table not found\n";
    }
}

exit
```

## الخطوة 5: تشغيل Migration

### الطريقة الآمنة (خطوة بخطوة):

```bash
# 1. تشغيل Migration في وضع التجربة (Dry Run)
# للأسف Laravel لا يدعم dry-run، لكن يمكننا التحقق من الـ SQL

php artisan tinker

# عرض SQL الذي سيتم تنفيذه
DB::enableQueryLog();

# محاكاة إضافة عمود
Schema::table('categories', function ($table) {
    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
});

# عرض الاستعلامات
dd(DB::getQueryLog());
```

### التشغيل الفعلي:

```bash
# تشغيل Migration
php artisan migrate

# إذا ظهرت أي أخطاء، لا تقلق، يمكن التراجع
```

## الخطوة 6: التحقق من النجاح

```bash
php artisan tinker

# التحقق من إضافة الأعمدة
$tables = ['categories', 'employees', 'expenses', 'feedback', 'invoice_sequences', 
           'salary_deliveries', 'stock_movements', 'cashier_shifts'];

foreach ($tables as $table) {
    $hasColumn = Schema::hasColumn($table, 'tenant_id');
    echo "$table: " . ($hasColumn ? '✅ Success' : '❌ Failed') . "\n";
}

exit
```

## الخطوة 7: التحقق من البيانات

```bash
php artisan tinker

# التحقق من عدم فقدان البيانات
echo "Categories: " . \App\Models\Category::withoutGlobalScope('tenant')->count() . "\n";
echo "Employees: " . \App\Models\Employee::withoutGlobalScope('tenant')->count() . "\n";
echo "Expenses: " . \App\Models\Expense::withoutGlobalScope('tenant')->count() . "\n";
echo "Products: " . \App\Models\Product::withoutGlobalScope('tenant')->count() . "\n";
echo "Orders: " . \App\Models\Order::withoutGlobalScope('tenant')->count() . "\n";

# التحقق من تحديث tenant_id
$categoriesWithTenant = \App\Models\Category::withoutGlobalScope('tenant')->whereNotNull('tenant_id')->count();
$categoriesTotal = \App\Models\Category::withoutGlobalScope('tenant')->count();
echo "Categories with tenant_id: $categoriesWithTenant / $categoriesTotal\n";

exit
```

## الخطوة 8: اختبار النظام

```bash
# اتبع الاختبارات في ملف TESTING_TENANT_SYSTEM.md
```

## في حالة وجود مشاكل

### إذا فشل Migration:

```bash
# التراجع عن آخر migration
php artisan migrate:rollback

# استعادة النسخة الاحتياطية
mysql -u root -p cashier_system < backup_before_tenant_YYYYMMDD_HHMMSS.sql
```

### إذا كانت البيانات القديمة بدون tenant_id:

```bash
php artisan tinker

# تحديث يدوي
$firstTenantId = \App\Models\User::whereNotNull('tenant_id')->value('tenant_id');

if ($firstTenantId) {
    \App\Models\Category::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
    \App\Models\Employee::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
    \App\Models\Expense::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
    \App\Models\Feedback::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
    \App\Models\InvoiceSequence::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
    
    echo "Updated all records with tenant_id: $firstTenantId\n";
}

exit
```

### إذا ظهرت أخطاء في الواجهة:

```bash
# مسح الـ cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# إعادة تحميل الصفحة
```

## الخطوة 9: التأكد من عمل Middleware

```bash
# تحقق من تسجيل Middleware
php artisan route:list

# يجب أن ترى EnsureTenantScope في middleware
```

## الخطوة 10: الاختبار النهائي

1. سجل دخول كمستخدم موجود
2. تصفح الصفحات المختلفة
3. أضف بيانات جديدة (فئة، موظف، مصروف)
4. تحقق من ظهور البيانات بشكل صحيح
5. سجل خروج وسجل دخول كمستخدم آخر (إن وجد)
6. تحقق من عدم ظهور بيانات المستخدم الأول

## ✅ قائمة التحقق النهائية

- [ ] تم عمل نسخة احتياطية من قاعدة البيانات
- [ ] تم تشغيل Migration بنجاح
- [ ] جميع الجداول تحتوي على عمود tenant_id
- [ ] لم يتم فقدان أي بيانات
- [ ] البيانات القديمة تم تحديثها بـ tenant_id
- [ ] النماذج (Models) تم تحديثها
- [ ] Middleware يعمل بشكل صحيح
- [ ] الاختبارات نجحت
- [ ] الواجهة تعمل بدون أخطاء
- [ ] العزل بين المستخدمين يعمل بشكل صحيح

## 🎉 تهانينا!

إذا اكتملت جميع الخطوات بنجاح، فإن نظام Multi-Tenancy الآن يعمل بشكل كامل!

## الدعم

إذا واجهت أي مشاكل:
1. راجع ملف TENANT_SYSTEM_DOCUMENTATION.md
2. راجع ملف TESTING_TENANT_SYSTEM.md
3. تحقق من logs في storage/logs/laravel.log
4. استعد النسخة الاحتياطية إذا لزم الأمر

