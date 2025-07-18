# صلاحيات الكاشير على إدارة الحضور والانصراف

## نظرة عامة
تم إضافة صلاحية جديدة لدور `cashier` للوصول إلى نظام إدارة الحضور والانصراف للموظفين، مع تقييد بعض الوظائف الإدارية.

## التحديثات المضافة

### 1. صلاحية جديدة
- **الصلاحية**: `manage employee attendance`
- **الوصف**: تسمح للمستخدم بتسجيل حضور وانصراف الموظفين
- **الممنوحون**: `admin` و `cashier`

### 2. Middleware جديد
- **الملف**: `app/Http/Middleware/EmployeeAttendanceMiddleware.php`
- **الوظيفة**: التحقق من صلاحية `manage employee attendance`
- **التسجيل**: في `bootstrap/app.php` باسم `employee.attendance`

### 3. تحديث Routes
- **التغيير**: تغيير middleware من `admin` إلى `employee.attendance`
- **النتيجة**: السماح للكاشير بالوصول لصفحات إدارة الموظفين

### 4. تحديث واجهة المستخدم

#### صفحة Dashboard
- إضافة `canManageAttendance` computed property
- تحديث شرط إظهار كارت الموظفين

#### صفحة إدارة الموظفين (Index.vue)
- إضافة `isAdmin` computed property
- إخفاء زر "إضافة موظف جديد" من الكاشير
- إخفاء زر "تعديل" من الكاشير
- تحديث العناوين والوصف حسب نوع المستخدم

## الصلاحيات حسب الدور

### دور Admin
- ✅ إضافة موظفين جدد
- ✅ تعديل بيانات الموظفين
- ✅ حذف الموظفين
- ✅ تسجيل الحضور والانصراف
- ✅ عرض التقارير
- ✅ إدارة جميع جوانب النظام

### دور Cashier
- ❌ إضافة موظفين جدد
- ❌ تعديل بيانات الموظفين
- ❌ حذف الموظفين
- ✅ تسجيل الحضور والانصراف
- ✅ عرض التقارير
- ✅ عرض قائمة الموظفين

## الملفات المحدثة

### Backend Files
```
database/seeders/RoleSeeder.php                    # إضافة صلاحية جديدة
app/Http/Middleware/EmployeeAttendanceMiddleware.php  # middleware جديد
bootstrap/app.php                                   # تسجيل middleware
routes/web.php                                      # تحديث routes
```

### Frontend Files
```
resources/js/Pages/Dashboard.vue                    # تحديث شرط إظهار كارت الموظفين
resources/js/Pages/Admin/Employees/Index.vue        # إخفاء أزرار الإدارة من الكاشير
```

## كيفية التطبيق

### 1. تشغيل Seeder
```bash
php artisan db:seed --class=RoleSeeder
```

### 2. التحقق من الصلاحيات
```bash
php artisan tinker
```
```php
// التحقق من الصلاحيات
$user = \App\Models\User::find(1);
$user->can('manage employee attendance');

// التحقق من الأدوار
$user->hasRole('cashier');
```

### 3. اختبار النظام
1. تسجيل دخول ككاشير
2. الوصول لصفحة الموظفين
3. تسجيل حضور وانصراف
4. التحقق من عدم ظهور أزرار الإدارة

## الأمان

### التحقق من الصلاحيات
- جميع routes محمية بـ middleware `employee.attendance`
- التحقق من الصلاحية في كل طلب
- رسائل خطأ واضحة للمستخدمين غير المصرح لهم

### تقييد الوصول
- الكاشير لا يمكنه إضافة/تعديل/حذف الموظفين
- الكاشير يمكنه فقط تسجيل الحضور والانصراف
- واجهة مستخدم مختلفة حسب الدور

## الميزات المستقبلية

### المخطط إضافتها
- [ ] صلاحيات أكثر تفصيلاً للحضور والانصراف
- [ ] تقارير خاصة بالكاشير
- [ ] إشعارات للحضور والانصراف
- [ ] سجل العمليات (audit log)

## الدعم والمساعدة

### في حالة وجود مشاكل
1. تحقق من تشغيل seeder بنجاح
2. تأكد من تعيين الدور الصحيح للمستخدم
3. تحقق من سجلات الخطأ في `storage/logs/laravel.log`
4. تأكد من تحديث cache إذا لزم الأمر

### أوامر مفيدة
```bash
# مسح cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# إعادة تشغيل seeder
php artisan db:seed --class=RoleSeeder

# عرض routes
php artisan route:list | grep employees
```

## ملاحظات مهمة

1. **التوافق**: النظام متوافق مع نظام tenant الحالي
2. **الأمان**: جميع العمليات محمية ومتحقق منها
3. **الواجهة**: واجهة عربية كاملة مع تصميم متجاوب
4. **الأداء**: لا يوجد تأثير على الأداء
5. **التطوير**: يمكن إضافة صلاحيات أكثر تفصيلاً حسب الحاجة 