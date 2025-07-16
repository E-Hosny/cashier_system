# حل مشاكل الاعتماديات والاتصال على السيرفر الحقيقي

## المشكلة المكتشفة

من السجلات نرى:
- ❌ `ERR_INTERNET_DISCONNECTED`
- ❌ `timeout of 5000ms exceeded`
- ❌ `Network Error` عند محاولة الوصول للسيرفر

## الأسباب المحتملة

### 1. مشاكل في إعدادات الشبكة
```bash
# التحقق من اتصال الشبكة
ping google.com
curl -I https://google.com

# التحقق من DNS
nslookup cashier-system.net
```

### 2. مشاكل في إعدادات Apache/Nginx
```bash
# التحقق من حالة الخدمة
sudo systemctl status apache2
sudo systemctl status nginx

# التحقق من السجلات
sudo tail -f /var/log/apache2/error.log
sudo tail -f /var/log/nginx/error.log
```

### 3. مشاكل في إعدادات PHP
```bash
# التحقق من إعدادات PHP
php -i | grep -i "max_execution_time\|memory_limit\|post_max_size"

# التحقق من إعدادات cURL
php -i | grep -i "curl"
```

### 4. مشاكل في إعدادات SSL/HTTPS
```bash
# التحقق من شهادة SSL
openssl s_client -connect cashier-system.net:443

# التحقق من إعدادات HTTPS
curl -I https://cashier-system.net
```

## الحلول المقترحة

### الحل 1: إصلاح إعدادات الشبكة
```bash
# إعادة تشغيل خدمات الشبكة
sudo systemctl restart networking
sudo systemctl restart systemd-resolved

# التحقق من إعدادات DNS
cat /etc/resolv.conf
```

### الحل 2: إصلاح إعدادات Apache
```bash
# التحقق من ملف التكوين
sudo nano /etc/apache2/sites-available/cashier-system.net.conf

# التأكد من وجود هذه الإعدادات
<VirtualHost *:80>
    ServerName cashier-system.net
    Redirect permanent / https://cashier-system.net/
</VirtualHost>

<VirtualHost *:443>
    ServerName cashier-system.net
    DocumentRoot /var/www/cashier_system/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /var/www/cashier_system/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/cashier-system.net_error.log
    CustomLog ${APACHE_LOG_DIR}/cashier-system.net_access.log combined
</VirtualHost>
```

### الحل 3: إصلاح إعدادات PHP
```bash
# تحديث إعدادات PHP
sudo nano /etc/php/8.1/apache2/php.ini

# إضافة/تحديث هذه الإعدادات
max_execution_time = 300
memory_limit = 512M
post_max_size = 100M
upload_max_filesize = 100M
allow_url_fopen = On
```

### الحل 4: إصلاح إعدادات Laravel
```bash
# في ملف .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cashier-system.net
ASSET_URL=https://cashier-system.net

# إعدادات قاعدة البيانات
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# إعدادات الجلسة
SESSION_DRIVER=file
SESSION_LIFETIME=1440
SESSION_SECURE_COOKIE=true
```

### الحل 5: إصلاح الصلاحيات
```bash
# إصلاح صلاحيات الملفات
sudo chown -R www-data:www-data /var/www/cashier_system
sudo chmod -R 755 /var/www/cashier_system
sudo chmod -R 775 /var/www/cashier_system/storage
sudo chmod -R 775 /var/www/cashier_system/bootstrap/cache
```

### الحل 6: مسح الكاش وإعادة التحميل
```bash
# مسح جميع أنواع الكاش
cd /var/www/cashier_system
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# إعادة تشغيل الخدمات
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

## التحقق من الحل

### 1. اختبار الاتصال المحلي
```bash
# اختبار من السيرفر نفسه
curl -I https://cashier-system.net
curl -I https://cashier-system.net/offline/check-connection
```

### 2. اختبار قاعدة البيانات
```bash
php artisan tinker --execute="echo 'DB Test: '; try { DB::table('users')->first(); echo 'OK'; } catch(Exception \$e) { echo 'ERROR: ' . \$e->getMessage(); }"
```

### 3. اختبار من المتصفح
```bash
# في المتصفح، افتح Developer Tools -> Console
fetch('/offline/check-connection')
  .then(response => response.json())
  .then(data => console.log(data))
  .catch(error => console.error(error));
```

## النتيجة المتوقعة

بعد تطبيق الحلول:
- ✅ **الاتصال بالإنترنت يعمل** بشكل طبيعي
- ✅ **جميع المسارات تعمل** بدون أخطاء
- ✅ **إنشاء طلب أوفلاين يعمل** بشكل طبيعي
- ✅ **طباعة الفاتورة تعمل** في وضع أوفلاين
- ✅ **لا توجد أخطاء Network Error**

## ملاحظات مهمة

1. **تأكد من أن السيرفر متصل بالإنترنت**
2. **تحقق من إعدادات Firewall**
3. **تأكد من صحة شهادة SSL**
4. **راقب سجلات Apache/Nginx**
5. **اختبر من أجهزة مختلفة**

---
**الحل الأكثر احتمالاً: إصلاح إعدادات Apache و SSL** 🔧 