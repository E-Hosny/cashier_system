# تشخيص وحل مشاكل وضع Offline على السيرفر

## المشكلة
النظام يعمل بشكل مثالي محلياً، لكن في وضع offline على السيرفر يحدث:
- Network Error
- فشل في إنشاء طلب أوفلاين
- خطأ في فحص الاتصال

## الأسباب المحتملة

### 1. مشاكل في إعدادات السيرفر
```bash
# التحقق من إعدادات PHP
php -i | grep -i "max_execution_time\|memory_limit\|post_max_size"

# التحقق من إعدادات Apache/Nginx
# التأكد من أن mod_rewrite مفعل
```

### 2. مشاكل في قاعدة البيانات
```bash
# التحقق من اتصال قاعدة البيانات
php artisan tinker --execute="echo 'DB Connection: '; try { DB::connection()->getPdo(); echo 'OK'; } catch(Exception \$e) { echo 'FAILED: ' . \$e->getMessage(); }"

# التحقق من وجود الجداول
php artisan tinker --execute="echo 'Tables: '; print_r(Schema::getAllTables());"
```

### 3. مشاكل في الصلاحيات
```bash
# التحقق من صلاحيات الملفات
ls -la storage/
ls -la bootstrap/cache/

# إصلاح الصلاحيات
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

### 4. مشاكل في التخزين المؤقت
```bash
# مسح جميع أنواع الكاش
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan optimize:clear
```

## خطوات التشخيص

### الخطوة 1: التحقق من السجلات
```bash
# فحص سجلات Laravel
tail -f storage/logs/laravel.log

# فحص سجلات Apache/Nginx
tail -f /var/log/apache2/error.log
# أو
tail -f /var/log/nginx/error.log
```

### الخطوة 2: اختبار الاتصال
```bash
# اختبار الاتصال بقاعدة البيانات
php artisan tinker --execute="echo 'Testing DB connection...'; try { DB::table('users')->first(); echo 'SUCCESS'; } catch(Exception \$e) { echo 'ERROR: ' . \$e->getMessage(); }"

# اختبار إنشاء طلب أوفلاين
curl -X POST http://your-domain.com/offline/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"total_price":100,"payment_method":"cash","items":[{"product_id":1,"product_name":"Test","quantity":1,"price":100,"size":"medium"}]}'
```

### الخطوة 3: التحقق من الإعدادات
```bash
# التحقق من ملف .env
cat .env | grep -E "(APP_ENV|APP_DEBUG|DB_|CACHE_)"

# التأكد من أن APP_DEBUG=true للتطوير
echo "APP_DEBUG=true" >> .env
```

## الحلول المقترحة

### الحل 1: إصلاح الصلاحيات
```bash
# إصلاح صلاحيات الملفات
sudo chown -R www-data:www-data /var/www/cashier_system
sudo chmod -R 755 /var/www/cashier_system
sudo chmod -R 775 /var/www/cashier_system/storage
sudo chmod -R 775 /var/www/cashier_system/bootstrap/cache
```

### الحل 2: إعادة تشغيل الخدمات
```bash
# إعادة تشغيل Apache
sudo systemctl restart apache2

# أو إعادة تشغيل Nginx
sudo systemctl restart nginx

# إعادة تشغيل PHP-FPM
sudo systemctl restart php8.1-fpm
```

### الحل 3: مسح الكاش وإعادة التحميل
```bash
# مسح جميع أنواع الكاش
php artisan optimize:clear

# إعادة تحميل التكوين
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### الحل 4: التحقق من إعدادات SSL/HTTPS
```bash
# إذا كان السيرفر يستخدم HTTPS، تأكد من أن جميع الطلبات تستخدم HTTPS
# في ملف .env
APP_URL=https://your-domain.com
ASSET_URL=https://your-domain.com
```

## اختبار الحل

### 1. اختبار الاتصال
```bash
# في المتصفح، افتح Developer Tools -> Console
# اكتب:
fetch('/offline/check-connection')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error(error));
```

### 2. اختبار إنشاء طلب أوفلاين
```bash
# في Console المتصفح
fetch('/offline/orders', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
  },
  body: JSON.stringify({
    total_price: 100,
    payment_method: 'cash',
    items: [{
      product_id: 1,
      product_name: 'Test Product',
      quantity: 1,
      price: 100,
      size: 'medium'
    }]
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error(error));
```

## النتيجة المتوقعة

بعد تطبيق الحلول:
- ✅ **فحص الاتصال يعمل** بدون أخطاء
- ✅ **إنشاء طلب أوفلاين يعمل** بشكل طبيعي
- ✅ **طباعة الفاتورة تعمل** في وضع أوفلاين
- ✅ **لا توجد أخطاء Network Error**

## ملاحظات مهمة

1. **اختبر كل حل على حدة** لتحديد السبب الدقيق
2. **راقب السجلات** أثناء الاختبار
3. **تأكد من أن قاعدة البيانات متاحة** ومتصل بها
4. **اختبر في متصفح مختلف** أو وضع incognito 