# أمثلة على نظام ترقيم الفواتير

## مثال 1: فواتير اليوم الحالي

```
التاريخ: 19 ديسمبر 2024
الوقت: 09:30 صباحاً

الفواتير المولدة:
- الفاتورة الأولى:  A2B3C4D5E  (التسلسل: 1)
- الفاتورة الثانية: F7G8H9I0J  (التسلسل: 2)
- الفاتورة الثالثة: K1L2M3N4O  (التسلسل: 3)
```

## مثال 2: فواتير يوم مختلف

```
التاريخ: 20 ديسمبر 2024
الوقت: 10:15 صباحاً

الفواتير المولدة:
- الفاتورة الأولى:  P5Q6R7S8T  (التسلسل: 1)
- الفاتورة الثانية: U9V0W1X2Y  (التسلسل: 2)
```

## مثال 3: فك التشفير

```
رقم الفاتورة: A2B3C4D5E

فك التشفير:
- تاريخ الفاتورة: 19 ديسمبر 2024
- التسلسل اليومي: 1
- اللاحقة العشوائية: 45
- صحة الرقم: ✅ صحيح
```

## مثال 4: التحقق من صحة الرقم

```
أرقام صحيحة:
✅ A2B3C4D5E
✅ F7G8H9I0J
✅ K1L2M3N4O

أرقام خاطئة:
❌ 123456789
❌ ABCDEFGHI
❌ A2B3C4D5F
```

## مثال 5: استخراج المعلومات

```php
// استخراج معلومات الفاتورة
$invoiceNumber = "A2B3C4D5E";
$info = InvoiceNumberService::getInvoiceInfo($invoiceNumber);

// النتيجة:
[
    'sequence' => 1,
    'date' => '2024-12-19',
    'formatted_date' => '2024-12-19',
    'is_today' => true,
    'day_number' => 1
]
```

## مثال 6: التصفر اليومي

```
اليوم الأول (19 ديسمبر):
- الفاتورة 1: A2B3C4D5E
- الفاتورة 2: F7G8H9I0J
- الفاتورة 3: K1L2M3N4O

اليوم الثاني (20 ديسمبر):
- الفاتورة 1: P5Q6R7S8T  ← يبدأ من 1 مرة أخرى
- الفاتورة 2: U9V0W1X2Y
```

## مثال 7: عدم الوضوح للزبون

```
الزبون يرى:
- الفاتورة رقم: A2B3C4D5E
- لا يمكن معرفة:
  * عدد الفواتير اليومية
  * التسلسل الحقيقي
  * تاريخ الفاتورة
  * معلومات أخرى

الإدارة ترى:
- الفاتورة رقم: A2B3C4D5E
- التسلسل اليومي: 1
- التاريخ: 19 ديسمبر 2024
- صحة الرقم: ✅
```

## مثال 8: التطبيق العملي

### في الكاشير:
```php
// عند إنشاء فاتورة جديدة
$order = Order::create([
    'total' => 150.00,
    'payment_method' => 'cash',
    'status' => 'completed',
    'invoice_number' => InvoiceNumberService::generateInvoiceNumber(),
]);
```

### في الطباعة:
```html
<h1>فاتورة رقم #A2B3C4D5E</h1>
<p>التاريخ: 19 ديسمبر 2024 09:30</p>
```

### في التقارير:
```php
// البحث عن فاتورة
$order = Order::where('invoice_number', 'A2B3C4D5E')->first();

// التحقق من صحة الرقم
if (InvoiceNumberService::isValidInvoiceNumber('A2B3C4D5E')) {
    echo "رقم فاتورة صحيح";
}
```

## مثال 9: الأمان

### مميزات الأمان:
1. **عدم الوضوح**: لا يمكن معرفة التسلسل الحقيقي
2. **التشفير**: الأرقام مشفرة بطريقة بسيطة
3. **التحقق**: يمكن التحقق من صحة الرقم
4. **التتبع**: يمكن تتبع الفاتورة من الرقم

### حدود الأمان:
1. **التشفير بسيط**: يمكن كسره بالتحليل العميق
2. **نمط ثابت**: نفس اليوم ينتج نفس النمط
3. **قابل للفك**: يمكن استخراج المعلومات

## مثال 10: التطوير المستقبلي

### تحسينات مقترحة:
1. **تشفير أقوى**: استخدام خوارزميات تشفير متقدمة
2. **أرقام خاصة**: أرقام مميزة للفواتير المهمة
3. **تخصيص**: إمكانية تخصيص تنسيق الأرقام
4. **نسخ احتياطية**: نظام نسخ احتياطية للأرقام

### مثال على التشفير المتقدم:
```php
// تشفير أقوى باستخدام مفتاح سري
$invoiceNumber = hash('sha256', $sequence . $date . $secretKey);
``` 