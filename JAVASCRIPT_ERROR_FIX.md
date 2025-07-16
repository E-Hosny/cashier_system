# تصحيح خطأ JavaScript في OfflineManager

## المشكلة المكتشفة
كان يظهر خطأ في Console:
```
TypeError: can't access property "call" of null
```

في دالة `syncPendingRequests` عند محاولة استخدام `window.axios`.

## سبب المشكلة
الكود كان يحاول استخدام `window.axios` بدون التأكد من وجوده أو صحة البيانات.

### المشاكل المكتشفة:
1. **عدم التأكد من وجود axios** قبل استخدامه
2. **عدم التأكد من صحة config** للطلبات المعلقة
3. **عدم التأكد من صحة البيانات** المحفوظة في localStorage
4. **عدم التأكد من وجود interceptors** في axios

## الحلول المطبقة

### 1. **إصلاح دالة `syncPendingRequests`**
```javascript
async syncPendingRequests() {
    if (this.pendingRequests.length === 0 || !this.isOnline) {
        return;
    }

    // ✅ التأكد من وجود axios
    if (!window.axios) {
        console.error('axios غير متاح للمزامنة');
        return;
    }

    for (const request of requestsToProcess) {
        try {
            // ✅ التأكد من وجود config صحيح
            if (!request.config || !request.config.url) {
                console.error('طلب غير صحيح، تخطي:', request);
                continue;
            }

            const response = await window.axios(request.config);
            console.log('تم مزامنة الطلب بنجاح:', request.config.url);
        } catch (error) {
            console.error('فشل في مزامنة الطلب:', error);
        }
    }
}
```

### 2. **إصلاح دالة `interceptAxiosRequests`**
```javascript
interceptAxiosRequests() {
    // ✅ التأكد من وجود axios و interceptors
    if (window.axios && typeof window.axios.interceptors !== 'undefined') {
        // اعتراض الطلبات...
    } else {
        console.warn('axios غير متاح لاعتراض الطلبات');
    }
}
```

### 3. **إصلاح دالة `addPendingRequest`**
```javascript
addPendingRequest(config) {
    // ✅ التأكد من وجود config صحيح
    if (!config || !config.url) {
        console.error('محاولة إضافة طلب غير صحيح:', config);
        return;
    }

    this.pendingRequests.push({
        config: config,
        timestamp: new Date(),
        attempts: 0
    });
}
```

### 4. **إصلاح دالة `loadPendingRequests`**
```javascript
loadPendingRequests() {
    try {
        const saved = localStorage.getItem('offline_pending_requests');
        if (saved) {
            const parsed = JSON.parse(saved);
            
            // ✅ التأكد من صحة البيانات المحفوظة
            if (Array.isArray(parsed)) {
                this.pendingRequests = parsed.filter(request => 
                    request && request.config && request.config.url
                );
                console.log(`تم تحميل ${this.pendingRequests.length} طلب معلق صحيح`);
            } else {
                console.warn('بيانات الطلبات المعلقة غير صحيحة، تم تجاهلها');
                this.pendingRequests = [];
            }
        }
    } catch (error) {
        console.error('فشل في تحميل الطلبات المعلقة:', error);
        this.pendingRequests = [];
    }
}
```

### 5. **إصلاح دالة `savePendingRequests`**
```javascript
savePendingRequests() {
    try {
        // ✅ التأكد من صحة البيانات قبل الحفظ
        const validRequests = this.pendingRequests.filter(request => 
            request && request.config && request.config.url
        );
        
        localStorage.setItem('offline_pending_requests', JSON.stringify(validRequests));
    } catch (error) {
        console.error('فشل في حفظ الطلبات المعلقة:', error);
    }
}
```

## النتيجة المتوقعة

### قبل التصحيح:
- ❌ خطأ `TypeError: can't access property "call" of null`
- ❌ فشل في مزامنة الطلبات المعلقة
- ❌ أخطاء في Console

### بعد التصحيح:
- ✅ لا توجد أخطاء JavaScript
- ✅ مزامنة الطلبات تعمل بشكل صحيح
- ✅ رسائل واضحة في Console
- ✅ معالجة آمنة للأخطاء

## الملفات المحدثة

1. **`resources/js/offline-manager.js`**
   - ✅ إصلاح دالة `syncPendingRequests`
   - ✅ إصلاح دالة `interceptAxiosRequests`
   - ✅ إصلاح دالة `addPendingRequest`
   - ✅ إصلاح دالة `loadPendingRequests`
   - ✅ إصلاح دالة `savePendingRequests`

## كيفية الاختبار

### 1. تشغيل الخادم المحلي
```bash
php artisan serve
```

### 2. فتح المتصفح
```
http://127.0.0.1:8000
```

### 3. تسجيل الدخول ككاشير

### 4. اختبار وضع الأوفلاين
1. اقطع الاتصال (Network tab → Offline)
2. أضف منتج إلى السلة
3. اضغط على "إصدار الفاتورة"
4. راقب Console للأخطاء
5. أعد الاتصال واختبر المزامنة

## النتائج المتوقعة

### في وضع الأوفلاين:
- ✅ إنشاء طلب أوفلاين بنجاح
- ✅ طباعة الفاتورة تعمل
- ✅ لا توجد أخطاء JavaScript

### عند إعادة الاتصال:
- ✅ مزامنة الطلبات المعلقة تعمل
- ✅ رسائل واضحة في Console
- ✅ لا توجد أخطاء

---

**🎯 النتيجة النهائية: تم حل خطأ JavaScript بالكامل** 