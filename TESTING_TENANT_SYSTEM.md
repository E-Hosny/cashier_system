# دليل اختبار نظام Multi-Tenancy

## الخطوات قبل الاختبار

### 1. تشغيل Migration
```bash
php artisan migrate
```

### 2. التحقق من نجاح Migration
```bash
php artisan tinker

# التحقق من إضافة العمود
Schema::hasColumn('categories', 'tenant_id');
Schema::hasColumn('employees', 'tenant_id');
Schema::hasColumn('expenses', 'tenant_id');
# يجب أن تعيد true لكل منها
```

## اختبارات الوظائف الأساسية

### 1. اختبار تعيين tenant_id تلقائياً

```bash
php artisan tinker

# تسجيل دخول كمستخدم
auth()->loginUsingId(1);

# إنشاء فئة جديدة
$category = \App\Models\Category::create(['name' => 'فئة اختبار']);

# التحقق من tenant_id
echo $category->tenant_id; // يجب أن يكون نفس tenant_id للمستخدم الحالي
echo auth()->user()->tenant_id;
```

### 2. اختبار العزل بين المستخدمين

```bash
php artisan tinker

# إنشاء مستخدمين للاختبار (إذا لم يكونوا موجودين)
$user1 = \App\Models\User::find(1);
$user2 = \App\Models\User::find(2);

# التأكد من وجود tenant_id
if (!$user1->tenant_id) {
    $user1->update(['tenant_id' => $user1->id]);
}
if (!$user2->tenant_id) {
    $user2->update(['tenant_id' => $user2->id]);
}

# تسجيل دخول كمستخدم 1
auth()->login($user1);

# إنشاء فئة للمستخدم 1
$cat1 = \App\Models\Category::create(['name' => 'فئة المستخدم 1']);
echo "Category 1 ID: " . $cat1->id . ", Tenant: " . $cat1->tenant_id . "\n";

# تسجيل دخول كمستخدم 2
auth()->login($user2);

# إنشاء فئة للمستخدم 2
$cat2 = \App\Models\Category::create(['name' => 'فئة المستخدم 2']);
echo "Category 2 ID: " . $cat2->id . ", Tenant: " . $cat2->tenant_id . "\n";

# المستخدم 2 يجب أن يرى فئته فقط
$categories = \App\Models\Category::all();
echo "User 2 sees " . $categories->count() . " categories\n";
echo "Categories: " . $categories->pluck('name')->implode(', ') . "\n";

# تسجيل دخول كمستخدم 1
auth()->login($user1);

# المستخدم 1 يجب أن يرى فئته فقط
$categories = \App\Models\Category::all();
echo "User 1 sees " . $categories->count() . " categories\n";
echo "Categories: " . $categories->pluck('name')->implode(', ') . "\n";

# عرض جميع الفئات بدون Global Scope
$allCategories = \App\Models\Category::withoutGlobalScope('tenant')->get();
echo "Total categories in database: " . $allCategories->count() . "\n";
```

### 3. اختبار الموظفين (Employees)

```bash
php artisan tinker

# تسجيل دخول كمستخدم 1
auth()->loginUsingId(1);

# إنشاء موظف
$emp1 = \App\Models\Employee::create([
    'name' => 'موظف المستخدم 1',
    'hourly_rate' => 50,
    'is_active' => true
]);

echo "Employee created with tenant_id: " . $emp1->tenant_id . "\n";

# تسجيل دخول كمستخدم 2
auth()->loginUsingId(2);

# محاولة الوصول للموظف
$employee = \App\Models\Employee::find($emp1->id);
echo "User 2 can access employee: " . ($employee ? 'YES (ERROR!)' : 'NO (CORRECT!)') . "\n";

# إنشاء موظف للمستخدم 2
$emp2 = \App\Models\Employee::create([
    'name' => 'موظف المستخدم 2',
    'hourly_rate' => 60,
    'is_active' => true
]);

# عرض موظفي المستخدم 2
$employees = \App\Models\Employee::all();
echo "User 2 sees " . $employees->count() . " employees\n";
```

### 4. اختبار المصروفات (Expenses)

```bash
php artisan tinker

# تسجيل دخول كمستخدم 1
auth()->loginUsingId(1);

# إنشاء مصروف
$expense1 = \App\Models\Expense::create([
    'description' => 'مصروف المستخدم 1',
    'amount' => 100,
    'expense_date' => now()
]);

# تسجيل دخول كمستخدم 2
auth()->loginUsingId(2);

# إنشاء مصروف
$expense2 = \App\Models\Expense::create([
    'description' => 'مصروف المستخدم 2',
    'amount' => 200,
    'expense_date' => now()
]);

# التحقق من العزل
$expenses = \App\Models\Expense::all();
echo "User 2 sees " . $expenses->count() . " expenses\n";
echo "Total amount: " . $expenses->sum('amount') . "\n";
```

### 5. اختبار الطلبات والمنتجات

```bash
php artisan tinker

# تسجيل دخول كمستخدم 1
auth()->loginUsingId(1);

# عرض المنتجات
$products = \App\Models\Product::all();
echo "User 1 has " . $products->count() . " products\n";

# عرض الطلبات
$orders = \App\Models\Order::all();
echo "User 1 has " . $orders->count() . " orders\n";

# تسجيل دخول كمستخدم 2
auth()->loginUsingId(2);

# عرض المنتجات
$products = \App\Models\Product::all();
echo "User 2 has " . $products->count() . " products\n";

# عرض الطلبات
$orders = \App\Models\Order::all();
echo "User 2 has " . $orders->count() . " orders\n";
```

### 6. اختبار InvoiceSequence

```bash
php artisan tinker

# تسجيل دخول كمستخدم 1
auth()->loginUsingId(1);

# الحصول على رقم فاتورة
$dateCode = now()->format('ymd');
$seq1 = \App\Models\InvoiceSequence::getNextSequence($dateCode);
echo "User 1 - Invoice sequence: " . $seq1 . "\n";

# تسجيل دخول كمستخدم 2
auth()->loginUsingId(2);

# الحصول على رقم فاتورة
$seq2 = \App\Models\InvoiceSequence::getNextSequence($dateCode);
echo "User 2 - Invoice sequence: " . $seq2 . "\n";

# يجب أن يكون كل مستخدم له تسلسله الخاص
# User 1 سيكون 1، User 2 سيكون 1 أيضاً (لأنه أول فاتورة له)
```

## اختبار البيانات الحالية

### التحقق من عدم فقدان البيانات

```bash
php artisan tinker

# عرض جميع الفئات (بدون Global Scope)
$allCategories = \App\Models\Category::withoutGlobalScope('tenant')->get();
echo "Total categories: " . $allCategories->count() . "\n";

# عرض جميع الموظفين
$allEmployees = \App\Models\Employee::withoutGlobalScope('tenant')->get();
echo "Total employees: " . $allEmployees->count() . "\n";

# عرض جميع المصروفات
$allExpenses = \App\Models\Expense::withoutGlobalScope('tenant')->get();
echo "Total expenses: " . $allExpenses->count() . "\n";

# التحقق من تحديث tenant_id للبيانات القديمة
echo "Categories without tenant_id: " . $allCategories->whereNull('tenant_id')->count() . "\n";
echo "Employees without tenant_id: " . $allEmployees->whereNull('tenant_id')->count() . "\n";
echo "Expenses without tenant_id: " . $allExpenses->whereNull('tenant_id')->count() . "\n";
```

## اختبار واجهة المستخدم

### 1. تسجيل الدخول كمستخدم مختلف
- سجل دخول كمستخدم 1
- أضف فئة جديدة
- أضف موظف جديد
- أضف مصروف جديد

### 2. تسجيل الدخول كمستخدم آخر
- سجل دخول كمستخدم 2
- تحقق من عدم ظهور بيانات المستخدم 1
- أضف بيانات جديدة للمستخدم 2

### 3. التحقق من العزل
- تأكد من أن كل مستخدم يرى بياناته فقط
- تأكد من عدم إمكانية الوصول لبيانات مستخدم آخر

## معالجة المشاكل المحتملة

### إذا ظهرت بيانات مستخدم آخر:
```bash
# تحقق من Global Scope
php artisan tinker
auth()->loginUsingId(1);
$categories = \App\Models\Category::all();
echo "Query: " . \App\Models\Category::toSql() . "\n";
```

### إذا لم يتم تعيين tenant_id:
```bash
# تحقق من Middleware
php artisan route:list --name=categories

# تحقق من booted method في Model
```

### إذا كانت البيانات القديمة بدون tenant_id:
```bash
php artisan tinker

# تحديث يدوي
$firstTenantId = \App\Models\User::whereNotNull('tenant_id')->value('tenant_id');
\App\Models\Category::whereNull('tenant_id')->update(['tenant_id' => $firstTenantId]);
```

## النتائج المتوقعة

✅ **يجب أن يحدث:**
- كل مستخدم يرى بياناته فقط
- عند إنشاء بيانات جديدة، يتم تعيين tenant_id تلقائياً
- لا يمكن الوصول لبيانات مستخدم آخر
- البيانات القديمة تم تحديثها بـ tenant_id

❌ **يجب ألا يحدث:**
- ظهور بيانات مستخدم آخر
- فقدان أي بيانات
- أخطاء في الاستعلامات
- بيانات بدون tenant_id

## التقرير النهائي

بعد إجراء جميع الاختبارات، قم بتوثيق:
1. عدد الجداول المحدثة: ___
2. عدد النماذج المحدثة: ___
3. عدد البيانات القديمة المحدثة: ___
4. هل جميع الاختبارات نجحت؟ نعم / لا
5. هل هناك أي مشاكل؟ ___

## الخلاصة

إذا نجحت جميع الاختبارات، فإن النظام جاهز للاستخدام مع ضمان العزل التام بين المستخدمين.

