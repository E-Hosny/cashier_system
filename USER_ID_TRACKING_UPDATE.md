# إضافة user_id للطلبات للاستعلام من Tinker

## الهدف

إضافة `user_id` إلى جدول الطلبات للتمكن من الاستعلام من Tinker ومعرفة من قام بكل طلب، **بدون عرض اسم الكاشير في الواجهة**.

## التحديثات المطبقة

### 1. إضافة عمود user_id
**ملف**: `database/migrations/2025_07_16_000000_add_user_id_to_orders_table.php`

```php
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained()->onDelete('set null');
});
```

### 2. تحديث نموذج Order
**ملف**: `app/Models/Order.php`

#### أ. إضافة user_id إلى fillable
```php
protected $fillable = [
    'total',
    'status',
    'payment_method',
    'tenant_id',
    'user_id', // جديد
    'cashier_shift_id',
    'invoice_number'
];
```

#### ب. إضافة العلاقة مع المستخدم
```php
public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
```

#### ج. تعيين المستخدم تلقائياً
```php
static::creating(function ($model) {
    if (auth()->check()) {
        $model->tenant_id = auth()->user()->tenant_id;
        $model->user_id = auth()->id(); // جديد
    }
});
```

## كيفية الاستخدام من Tinker

### 1. معرفة من قام بطلب محدد
```bash
php artisan tinker
```

```php
// معرفة من قام بالطلب رقم 1
$order = Order::with('user')->find(1);
echo "الطلب رقم: " . $order->id;
echo "الكاشير: " . $order->user->name;
echo "التاريخ: " . $order->created_at;
```

### 2. معرفة جميع طلبات مستخدم محدد
```php
// جميع طلبات المستخدم رقم 5
$userOrders = Order::where('user_id', 5)->get();
foreach($userOrders as $order) {
    echo "طلب رقم: " . $order->id . " - المبلغ: " . $order->total . "\n";
}
```

### 3. إحصائيات حسب المستخدم
```php
// إجمالي مبيعات كل مستخدم
$userStats = Order::selectRaw('user_id, COUNT(*) as orders_count, SUM(total) as total_sales')
    ->with('user')
    ->groupBy('user_id')
    ->get();

foreach($userStats as $stat) {
    echo $stat->user->name . ": " . $stat->orders_count . " طلب - " . $stat->total_sales . " جنيه\n";
}
```

### 4. البحث عن طلبات في فترة محددة
```php
// طلبات اليوم الحالي مع معرفة الكاشير
$todayOrders = Order::with('user')
    ->whereDate('created_at', today())
    ->get();

foreach($todayOrders as $order) {
    echo "طلب " . $order->id . " - الكاشير: " . $order->user->name . " - المبلغ: " . $order->total . "\n";
}
```

### 5. البحث عن طلبات مستخدم محدد في فترة زمنية
```php
// طلبات المستخدم رقم 3 في الأسبوع الماضي
$lastWeekOrders = Order::with('user')
    ->where('user_id', 3)
    ->whereBetween('created_at', [now()->subWeek(), now()])
    ->get();

foreach($lastWeekOrders as $order) {
    echo "طلب " . $order->id . " - التاريخ: " . $order->created_at . " - المبلغ: " . $order->total . "\n";
}
```

## كيفية تطبيق التحديثات

### 1. تشغيل Migration
```bash
php artisan migrate
```

### 2. مسح Cache
```bash
php artisan cache:clear
php artisan config:clear
```

### 3. اختبار النظام
```bash
php artisan tinker
```

```php
// اختبار إنشاء طلب جديد
$order = Order::create([
    'total' => 100,
    'payment_method' => 'cash',
    'status' => 'completed'
]);

// التأكد من حفظ user_id
echo $order->user_id; // يجب أن يظهر معرف المستخدم الحالي
```

## الفوائد

### 1. تتبع المسؤولية
- معرفة من قام بكل طلب
- تتبع الأخطاء والمشاكل
- تحسين الأداء والمسؤولية

### 2. تحليل البيانات
- إحصائيات مفصلة حسب المستخدم
- تحليل أداء كل موظف
- تقارير مخصصة

### 3. الأمان والمراقبة
- تتبع جميع العمليات
- منع الاحتيال والأخطاء
- تحسين إجراءات الأمان

## ملاحظات مهمة

1. **البيانات الموجودة**: الطلبات القديمة لن تحتوي على user_id
2. **التوافق**: النظام سيعمل مع أو بدون user_id
3. **الأمان**: يمكن حذف المستخدم مع الاحتفاظ بالطلبات
4. **الخصوصية**: لا يتم عرض اسم الكاشير في الواجهة، فقط للاستعلام من Tinker 