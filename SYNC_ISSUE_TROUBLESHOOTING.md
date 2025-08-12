# دليل استكشاف وإصلاح مشاكل المزامنة

## 🚨 المشكلة الحالية

**المشكلة**: تم إنشاء 5 طلبات أوفلاين ولكن تمت مزامنة طلب واحد فقط.

## 🔍 خطوات التشخيص

### 1. فحص الطلبات الأوفلاين في قاعدة البيانات

```sql
-- فحص جميع الطلبات الأوفلاين
SELECT id, offline_id, status, invoice_number, total, created_at, sync_attempted_at, sync_error 
FROM offline_orders 
WHERE status IN ('pending_sync', 'failed', 'syncing') 
ORDER BY created_at DESC;
```

### 2. فحص ملفات السجل

```bash
# فحص آخر سجلات المزامنة
tail -f storage/logs/laravel.log | grep -E "(مزامنة|sync|offline)"

# أو فحص سجلات اليوم
grep -E "(مزامنة|sync|offline)" storage/logs/laravel-$(date +%Y-%m-%d).log
```

### 3. فحص حالة الأقفال

```sql
-- في قاعدة البيانات أو cache
-- البحث عن مفاتيح الأقفال:
-- sync_offline_orders_{user_id}
-- sync_quick_lock_{user_id}
-- invoice_numbering_system_lock
```

## 🛠️ الحلول المحتملة

### الحل الأول: إعادة تعيين حالة الطلبات العالقة

```sql
-- إعادة تعيين الطلبات في حالة 'syncing' إلى 'pending_sync'
UPDATE offline_orders 
SET status = 'pending_sync', sync_error = NULL
WHERE status = 'syncing' 
AND sync_attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE);
```

### الحل الثاني: مسح الأقفال المعلقة

```bash
# إذا كنت تستخدم Redis
redis-cli FLUSHDB

# أو إذا كنت تستخدم file cache
php artisan cache:clear
```

### الحل الثالث: فحص يدوي للطلبات

```sql
-- فحص الطلبات المزامنة اليوم
SELECT COUNT(*) as synced_today 
FROM orders 
WHERE DATE(created_at) = CURDATE();

-- فحص الطلبات الأوفلاين المعلقة
SELECT COUNT(*) as pending_offline
FROM offline_orders 
WHERE status IN ('pending_sync', 'failed');
```

### الحل الرابع: تشغيل المزامنة يدوياً

```bash
# من خلال متصفح المطور (Developer Console)
# افتح الصفحة واكتب:
if (window.offlineManager) {
    window.offlineManager.autoSyncOfflineOrders();
}

# أو من خلال طلب مباشر
fetch('/offline/sync', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    }
}).then(r => r.json()).then(console.log);
```

## 🔧 نصائح لمنع المشاكل

### 1. مراقبة السجلات بانتظام

```bash
# إنشاء alias لمراقبة السجلات
alias watch-sync="tail -f storage/logs/laravel.log | grep -E '(مزامنة|sync|offline)'"
```

### 2. فحص دوري للطلبات المعلقة

```sql
-- استعلام للفحص الدوري
SELECT 
    status,
    COUNT(*) as count,
    MIN(created_at) as oldest,
    MAX(created_at) as newest
FROM offline_orders 
GROUP BY status;
```

### 3. تنظيف دوري للطلبات القديمة

```sql
-- حذف الطلبات المزامنة بنجاح والأقدم من 7 أيام
DELETE FROM offline_orders 
WHERE status = 'synced' 
AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## 📊 مؤشرات يجب مراقبتها

1. **عدد الطلبات المعلقة**: يجب أن يكون 0 أو قريباً من 0
2. **وقت آخر مزامنة**: يجب أن يكون حديثاً
3. **رسائل الخطأ**: يجب عدم وجود أخطاء متكررة
4. **حالة الأقفال**: يجب أن تكون مفتوحة عادة

## 🎯 الخطوات التالية

1. **فحص قاعدة البيانات**: تأكد من حالة الطلبات الأوفلاين
2. **فحص السجلات**: ابحث عن رسائل الخطأ أو التخطي
3. **مسح الأقفال**: امسح أي أقفال معلقة
4. **إعادة المزامنة**: جرب المزامنة يدوياً
5. **مراقبة النتائج**: تأكد من نجاح المزامنة

## 🚨 تحديثات الحماية الأخيرة

تم إضافة طبقات حماية قوية جداً لمنع التكرار، والتي قد تكون **مفرطة الحماية**:

1. ✅ **قفل المزامنة العام**: 10 دقائق
2. ✅ **قفل سريع**: 5 ثوانٍ  
3. ✅ **فحص رقم الفاتورة**: في قاعدة البيانات
4. ✅ **فحص offline_id**: في metadata
5. ✅ **فحص المحتوى المتطابق**: تطابق كامل للعناصر
6. ✅ **قفل الطلب الواحد**: 5 دقائق

**المشكلة المحتملة**: هذه الحماية قد تمنع مزامنة طلبات صحيحة!

## 💡 التوصية

1. فحص السجلات لمعرفة سبب تخطي الطلبات
2. التأكد من عدم وجود أقفال معلقة
3. إعادة تعيين حالة الطلبات العالقة
4. تجربة المزامنة يدوياً 