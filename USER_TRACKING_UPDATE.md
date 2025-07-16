# تحديث تتبع المستخدم في الطلبات

## الوضع الحالي

### ✅ النظام يدعم تتبع المستخدم بالفعل!

النظام الحالي يحتوي على آليات لتتبع من قام بتنفيذ الطلب:

#### 1. **من خلال وردية الكاشير**:
- جدول `orders` يحتوي على `cashier_shift_id`
- جدول `cashier_shifts` يحتوي على `user_id`
- يمكن معرفة الكاشير من خلال العلاقة بين الجداول

#### 2. **من خلال tenant_id**:
- جدول `orders` يحتوي على `tenant_id`
- يمكن معرفة المستخدم من خلال هذا المعرف

## التحسينات المضافة

### 1. إضافة عمود مباشر للمستخدم
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

### 3. تحديث تقارير المبيعات
**ملف**: `app/Http/Controllers/Admin/SalesReportController.php`

إضافة معلومات المستخدم للاستعلام:
```php
// جلب معلومات المستخدمين لكل طلب
$orderIds = $salesQuery->pluck('order_id')->unique();
$ordersWithUsers = Order::with('user')
    ->whereIn('id', $orderIds)
    ->get(['id', 'user_id', 'created_at'])
    ->keyBy('id');
```

### 4. تحديث الواجهة الأمامية
**ملف**: `resources/js/Pages/Admin/SalesReport.vue`

#### أ. إضافة عمود الكاشير
```vue
<th class="p-4">الكاشير</th>
```

#### ب. عرض اسم الكاشير
```vue
<td class="p-4 text-purple-600" data-label="الكاشير">{{ getCashierNames(sale) }}</td>
```

## كيفية الاستخدام

### 1. معرفة من قام بالطلب
```php
// الطريقة المباشرة (بعد التحديث)
$order = Order::with('user')->find($orderId);
$cashierName = $order->user->name;

// الطريقة القديمة (من خلال وردية الكاشير)
$order = Order::with('cashierShift.user')->find($orderId);
$cashierName = $order->cashierShift->user->name;
```

### 2. في تقارير المبيعات
- سيظهر عمود "الكاشير" في جدول المبيعات
- يمكن معرفة من قام ببيع كل منتج

### 3. في الفواتير
- يمكن إضافة اسم الكاشير في الفاتورة
- تتبع المسؤولية لكل طلب

## الفوائد

### 1. تتبع المسؤولية
- معرفة من قام بكل طلب
- تتبع الأخطاء والمشاكل
- تحسين الأداء والمسؤولية

### 2. تحسين التقارير
- تقارير مفصلة حسب الكاشير
- تحليل أداء كل موظف
- توزيع المهام بشكل أفضل

### 3. الأمان والمراقبة
- تتبع جميع العمليات
- منع الاحتيال والأخطاء
- تحسين إجراءات الأمان

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
- إنشاء طلب جديد
- التأكد من حفظ user_id
- فحص تقارير المبيعات

## ملاحظات مهمة

1. **البيانات الموجودة**: الطلبات القديمة لن تحتوي على user_id
2. **التوافق**: النظام سيعمل مع أو بدون user_id
3. **الأمان**: يمكن حذف المستخدم مع الاحتفاظ بالطلبات 