# حل مشكلة انتهاء صلاحية الجلسة على السيرفر

## المشكلة المكتشفة

من السجلات نرى أن:
- ✅ **إنشاء الطلبات في وضع offline يعمل بشكل مثالي**
- ❌ **طباعة الفاتورة تفشل** بسبب انتهاء صلاحية الجلسة
- ❌ **مسارات API تعيد توجيه إلى تسجيل الدخول**

## السبب

المستخدم غير مسجل دخول أو انتهت صلاحية الجلسة، مما يسبب:
- `HTTP/1.1 302 Found` -> `Location: /login`
- فشل في الوصول لمسارات API
- فشل في طباعة الفاتورة

## الحلول

### الحل 1: تسجيل الدخول مرة أخرى
1. اذهب إلى `https://cashier-system.net/login`
2. سجل دخولك مرة أخرى
3. اختبر النظام

### الحل 2: إطالة مدة الجلسة
```bash
# في ملف .env على السيرفر
SESSION_LIFETIME=1440  # 24 ساعة بدلاً من 2 ساعة
```

### الحل 3: إصلاح إعدادات الجلسة
```bash
# في ملف config/session.php
'lifetime' => env('SESSION_LIFETIME', 1440), // 24 ساعة
'expire_on_close' => false,
'secure' => true, // للتأكد من استخدام HTTPS
'same_site' => 'lax',
```

### الحل 4: مسح الكاش وإعادة تشغيل
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
sudo systemctl restart apache2
```

## التحقق من الحل

### 1. تسجيل الدخول
```bash
# اذهب إلى المتصفح
https://cashier-system.net/login
```

### 2. اختبار المسارات
```bash
# في Console المتصفح (بعد تسجيل الدخول)
fetch('/offline/check-connection')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error(error));
```

### 3. اختبار طباعة الفاتورة
```bash
# في المتصفح، اذهب إلى
https://cashier-system.net/offline/invoice/OFF_20250715_073157_eTO9TzkU
```

## النتيجة المتوقعة

بعد تسجيل الدخول:
- ✅ **جميع المسارات تعمل** بدون إعادة توجيه
- ✅ **طباعة الفاتورة تعمل** في وضع offline
- ✅ **لا توجد أخطاء Network Error**
- ✅ **النظام يعمل بشكل طبيعي**

## منع حدوث المشكلة مستقبلاً

### 1. إطالة مدة الجلسة
```bash
# في .env
SESSION_LIFETIME=1440
```

### 2. إضافة "Remember Me"
```bash
# في نموذج تسجيل الدخول
<input type="checkbox" name="remember" id="remember">
```

### 3. مراقبة الجلسات
```bash
# في لوحة التحكم، أضف مؤشر لحالة الجلسة
```

---
**الحل السريع: سجل دخولك مرة أخرى في المتصفح!** 🔐 