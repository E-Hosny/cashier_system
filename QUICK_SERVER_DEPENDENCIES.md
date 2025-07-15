# حل سريع لمشاكل الاعتماديات على السيرفر

## المشكلة
`ERR_INTERNET_DISCONNECTED` و `Network Error` على السيرفر الحقيقي

## الحل السريع (جرب بالترتيب)

### 1. التحقق من الاتصال
```bash
# اختبار الاتصال بالإنترنت
ping google.com
curl -I https://google.com

# اختبار الاتصال بالموقع
curl -I https://cashier-system.net
```

### 2. إصلاح إعدادات Apache
```bash
# التحقق من حالة Apache
sudo systemctl status apache2

# إعادة تشغيل Apache
sudo systemctl restart apache2

# التحقق من السجلات
sudo tail -f /var/log/apache2/error.log
```

### 3. إصلاح الصلاحيات
```bash
sudo chown -R www-data:www-data /var/www/cashier_system
sudo chmod -R 755 /var/www/cashier_system
sudo chmod -R 775 /var/www/cashier_system/storage
sudo chmod -R 775 /var/www/cashier_system/bootstrap/cache
```

### 4. مسح الكاش
```bash
cd /var/www/cashier_system
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
```

### 5. إعادة تشغيل الخدمات
```bash
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

## إذا لم يعمل الحل

### تحقق من إعدادات SSL
```bash
# التحقق من شهادة SSL
openssl s_client -connect cashier-system.net:443

# التحقق من ملف التكوين
sudo nano /etc/apache2/sites-available/cashier-system.net.conf
```

### تحقق من إعدادات PHP
```bash
# التحقق من إعدادات PHP
php -i | grep -i "max_execution_time\|memory_limit"

# تحديث إعدادات PHP
sudo nano /etc/php/8.1/apache2/php.ini
```

### تحقق من ملف .env
```bash
# التأكد من الإعدادات الصحيحة
cat .env | grep -E "(APP_ENV|APP_DEBUG|APP_URL|DB_)"

# تحديث الإعدادات
echo "APP_ENV=production" >> .env
echo "APP_DEBUG=false" >> .env
echo "APP_URL=https://cashier-system.net" >> .env
```

## اختبار الحل

### 1. اختبار محلي
```bash
curl -I https://cashier-system.net/offline/check-connection
```

### 2. اختبار من المتصفح
```javascript
// في Console المتصفح
fetch('/offline/check-connection')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error(error));
```

## النتيجة المتوقعة
- ✅ الاتصال بالإنترنت يعمل
- ✅ جميع المسارات تعمل
- ✅ إنشاء طلب أوفلاين يعمل
- ✅ طباعة الفاتورة تعمل
- ✅ لا توجد أخطاء Network Error

---
**جرب الحلول بالترتيب واختبر بعد كل خطوة** 