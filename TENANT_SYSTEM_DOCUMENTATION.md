# توثيق نظام Multi-Tenancy

## نظرة عامة
تم تطبيق نظام Multi-Tenancy كامل على النظام لضمان العزل التام بين بيانات المستخدمين المختلفين.

## التغييرات المطبقة

### 1. قاعدة البيانات (Database)

#### الجداول التي تم إضافة `tenant_id` لها:
- ✅ `users` (موجود مسبقاً)
- ✅ `products` (موجود مسبقاً)
- ✅ `orders` (موجود مسبقاً)
- ✅ `order_items` (موجود مسبقاً)
- ✅ `purchases` (موجود مسبقاً)
- ✅ `categories` (تم إضافته)
- ✅ `employees` (تم إضافته)
- ✅ `employee_attendances` (تم إضافته)
- ✅ `expenses` (تم إضافته)
- ✅ `feedback` (تم إضافته)
- ✅ `invoice_sequences` (تم إضافته)
- ✅ `salary_deliveries` (تم إضافته)
- ✅ `stock_movements` (تم إضافته)
- ✅ `cashier_shifts` (تم إضافته)

#### Migration الرئيسي:
```
database/migrations/2025_10_23_000000_add_tenant_id_to_all_tables.php
```

### 2. النماذج (Models)

تم تحديث جميع النماذج لتشمل:

#### أ. إضافة `tenant_id` إلى `$fillable`:
```php
protected $fillable = [
    // ... الحقول الأخرى
    'tenant_id',
];
```

#### ب. إضافة علاقة `tenant`:
```php
public function tenant()
{
    return $this->belongsTo(User::class, 'tenant_id');
}
```

#### ج. إضافة Global Scope:
```php
protected static function booted()
{
    static::addGlobalScope('tenant', function (Builder $query) {
        if (auth()->check()) {
            $query->where('tenant_id', auth()->user()->tenant_id);
        }
    });

    static::creating(function ($model) {
        if (auth()->check()) {
            $model->tenant_id = auth()->user()->tenant_id;
        }
    });
}
```

#### النماذج المحدثة:
- ✅ `Category`
- ✅ `Employee`
- ✅ `EmployeeAttendance`
- ✅ `Expense`
- ✅ `Feedback`
- ✅ `InvoiceSequence`
- ✅ `SalaryDelivery`
- ✅ `StockMovement`
- ✅ `CashierShift`
- ✅ `Product` (موجود مسبقاً)
- ✅ `Order` (موجود مسبقاً)
- ✅ `Purchase` (موجود مسبقاً)

### 3. Middleware

تم إنشاء Middleware جديد:
```
app/Http/Middleware/EnsureTenantScope.php
```

الوظائف:
- التحقق من وجود `tenant_id` للمستخدم
- تعيين `tenant_id` تلقائياً إذا لم يكن موجوداً
- يعمل على جميع طلبات الويب

تم تسجيله في `bootstrap/app.php`:
```php
$middleware->web(append: [
    \App\Http\Middleware\EnsureTenantScope::class,
]);
```

### 4. Controllers

Controllers لا تحتاج إلى تعديلات كبيرة لأن:
- Global Scopes تعمل تلقائياً على جميع الاستعلامات
- عند إنشاء سجل جديد، يتم تعيين `tenant_id` تلقائياً

## كيفية العمل

### 1. عند تسجيل مستخدم جديد:
```php
// في CreateNewUser.php
$user = User::create([...]);
$user->update(['tenant_id' => $user->id]); // المستخدم هو tenant لنفسه
```

### 2. عند إنشاء بيانات جديدة:
```php
// تلقائياً يتم إضافة tenant_id
Category::create(['name' => 'فئة جديدة']);
// سيتم تعيين tenant_id = auth()->user()->tenant_id تلقائياً
```

### 3. عند استرجاع البيانات:
```php
// تلقائياً يتم فلترة البيانات حسب tenant_id
$categories = Category::all();
// سيتم إضافة WHERE tenant_id = auth()->user()->tenant_id تلقائياً
```

### 4. إذا كنت تريد تجاوز Global Scope:
```php
// استخدم withoutGlobalScope
$allCategories = Category::withoutGlobalScope('tenant')->get();
```

## حماية البيانات الحالية

### Migration يحافظ على البيانات:
1. يضيف `tenant_id` كحقل nullable
2. يحدث البيانات الموجودة بناءً على العلاقات:
   - `cashier_shifts` من `user_id`
   - `stock_movements` من `product_id`
   - `employee_attendances` من `employee_id`
   - `salary_deliveries` من `employee_id`
3. للجداول بدون علاقة، يستخدم أول `tenant_id` موجود

## تشغيل النظام

### 1. تشغيل Migration:
```bash
php artisan migrate
```

### 2. التحقق من البيانات:
```bash
php artisan tinker

# التحقق من المستخدمين
User::all()->pluck('tenant_id', 'id');

# التحقق من الفئات
Category::withoutGlobalScope('tenant')->get();
```

### 3. اختبار العزل:
```bash
# تسجيل دخول كمستخدم 1
auth()->loginUsingId(1);
Category::all(); // سيعرض فقط فئات المستخدم 1

# تسجيل دخول كمستخدم 2
auth()->loginUsingId(2);
Category::all(); // سيعرض فقط فئات المستخدم 2
```

## الميزات

### ✅ العزل التام:
- كل مستخدم يرى بياناته فقط
- لا يمكن الوصول لبيانات مستخدم آخر

### ✅ الأمان:
- Global Scopes تعمل تلقائياً
- لا حاجة لتذكر إضافة `where('tenant_id', ...)` في كل استعلام

### ✅ السهولة:
- الكود الموجود يعمل بدون تعديلات
- التعيين التلقائي للـ `tenant_id`

### ✅ المرونة:
- يمكن تجاوز Global Scope عند الحاجة
- يمكن إضافة مستخدمين فرعيين لنفس الـ tenant

## الاستخدام المتقدم

### إضافة مستخدم فرعي:
```php
// مستخدم رئيسي (tenant)
$mainUser = User::find(1);

// إنشاء مستخدم فرعي
$subUser = User::create([
    'name' => 'موظف',
    'email' => 'employee@example.com',
    'password' => Hash::make('password'),
    'tenant_id' => $mainUser->id, // نفس tenant_id
]);
```

### الحصول على جميع مستخدمي tenant معين:
```php
$tenant = User::find(1);
$allUsers = $tenant->users; // جميع المستخدمين التابعين له
```

## الملاحظات المهمة

1. **الفئات (Categories)**: الآن كل tenant له فئاته الخاصة
2. **الموظفين (Employees)**: كل tenant له موظفيه الخاصين
3. **الفواتير (Invoice Sequences)**: كل tenant له تسلسل فواتيره الخاص
4. **المصروفات (Expenses)**: معزولة بين المستخدمين
5. **الـ Feedback**: كل tenant يرى تقييماته فقط

## الدعم والمساعدة

إذا واجهت أي مشاكل:
1. تأكد من تشغيل Migration
2. تحقق من وجود `tenant_id` للمستخدم الحالي
3. استخدم `withoutGlobalScope('tenant')` للتشخيص
4. راجع logs في `storage/logs/laravel.log`

## التحديثات المستقبلية

يمكن إضافة:
- [ ] صفحة إدارة المستخدمين الفرعيين
- [ ] صلاحيات مختلفة للمستخدمين الفرعيين
- [ ] تقارير مجمعة لجميع المستخدمين (للـ Super Admin)
- [ ] نسخ احتياطي منفصل لكل tenant

