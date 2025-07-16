# حل سريع لمشاكل وضع Offline على السيرفر

## المشكلة
Network Error و فشل في إنشاء طلب أوفلاين على السيرفر فقط

## الحل السريع (جرب بالترتيب)

### 1. إصلاح الصلاحيات
```bash
sudo chown -R www-data:www-data /var/www/cashier_system
sudo chmod -R 755 /var/www/cashier_system
sudo chmod -R 775 /var/www/cashier_system/storage
sudo chmod -R 775 /var/www/cashier_system/bootstrap/cache
```

### 2. مسح الكاش
```bash
cd /var/www/cashier_system
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

### 3. إعادة تشغيل الخدمات
```bash
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

### 4. التحقق من السجلات
```bash
tail -f storage/logs/laravel.log
```

### 5. اختبار الاتصال
```bash
# في متصفح السيرفر، افتح Developer Tools -> Console
fetch('/offline/check-connection')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error(error));
```

## إذا لم يعمل الحل

### تحقق من قاعدة البيانات
```bash
php artisan tinker --execute="echo 'DB Test: '; try { DB::table('users')->first(); echo 'OK'; } catch(Exception \$e) { echo 'ERROR: ' . \$e->getMessage(); }"
```

### تحقق من إعدادات .env
```bash
cat .env | grep -E "(APP_ENV|APP_DEBUG|DB_|CACHE_)"
```

### تأكد من أن APP_DEBUG=true
```bash
echo "APP_DEBUG=true" >> .env
```

## النتيجة المتوقعة
- ✅ فحص الاتصال يعمل
- ✅ إنشاء طلب أوفلاين يعمل
- ✅ طباعة الفاتورة تعمل
- ✅ لا توجد أخطاء Network Error

---
**جرب الحلول بالترتيب واختبر بعد كل خطوة** 