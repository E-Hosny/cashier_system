# دليل رفع النظام على السيرفر

## المتطلبات الأساسية:
- PHP 8.1+
- MySQL 8.0+
- HTTPS (مطلوب للـ Service Worker)
- SSL Certificate

## خطوات الرفع:

### 1. تحضير الملفات:
```bash
composer install --optimize-autoloader --no-dev
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2. رفع الملفات:
- ارفع جميع الملفات إلى السيرفر
- تأكد من وجود ملفات PWA:
  - public/sw.js
  - public/manifest.json
  - public/images/mylogo.png

### 3. إعداد قاعدة البيانات:
```bash
php artisan migrate
php artisan db:seed
```

### 4. إعدادات البيئة:
```env
APP_ENV=production
APP_DEBUG=false
OFFLINE_ENABLED=true
CACHE_VERSION=v2
```

### 5. اختبار النظام:
- افتح الموقع مع إنترنت
- تأكد من تسجيل Service Worker
- اختبر العمل بدون إنترنت

## ملاحظات مهمة:
- تأكد من وجود HTTPS
- اختبر على أجهزة مختلفة
- راقب أداء النظام
- احتفظ بنسخة احتياطية
