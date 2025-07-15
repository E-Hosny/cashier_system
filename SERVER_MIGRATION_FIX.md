# حل سريع لمشكلة الهجرة على السيرفر

## المشكلة
```
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'orders' already exists
```

## الحل السريع

### 1. حذف ملف الهجرة المكرر
```bash
rm database/migrations/2025_01_15_000001_create_orders_table.php
```

### 2. مسح الكاش
```bash
php artisan config:clear
php artisan cache:clear
```

### 3. تشغيل الهجرات
```bash
php artisan migrate
```

## التحقق من الحل
```bash
php artisan migrate:status
```

## النتيجة المتوقعة
- ✅ جميع الهجرات تعمل بدون أخطاء
- ✅ النظام يعمل بشكل طبيعي
- ✅ جميع الجداول موجودة ومحدثة

---
**ملاحظة:** هذا الحل آمن لأن جدول `orders` موجود بالفعل في قاعدة البيانات. 