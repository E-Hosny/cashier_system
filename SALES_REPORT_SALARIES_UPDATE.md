# إضافة إجمالي الرواتب إلى تقرير المبيعات

## المطلوب
إضافة إجمالي الرواتب إلى صفحة تقرير المبيعات بعد خانات إجمالي المبيعات وإجمالي المصروفات.

## التحديثات المنجزة

### 1. تحديث SalesReportController.php

#### إضافة حساب إجمالي الرواتب:
```php
// حساب إجمالي الرواتب للموظفين في الفترة المحددة
$totalSalaries = \App\Models\Employee::where('is_active', true)->get()->sum(function($employee) use ($dateFrom, $dateTo) {
    return $employee->getTotalAmountForPeriod($dateFrom, $dateTo);
});
```

#### إضافة totalSalaries إلى البيانات المرسلة:
```php
return Inertia::render('Admin/SalesReport', [
    // ... البيانات الأخرى
    'totalSalaries' => $totalSalaries,
    // ...
]);
```

### 2. تحديث Employee Model

#### إضافة دالة getTotalAmountForPeriod:
```php
/**
 * الحصول على إجمالي المبلغ المستحق لفترة محددة (مع مراعاة الفترات الزمنية)
 */
public function getTotalAmountForPeriod($startDate, $endDate = null)
{
    if ($endDate === null) {
        $endDate = $startDate;
    }

    // إذا كان نفس اليوم، نستخدم منطق اليوم الواحد
    if ($startDate === $endDate) {
        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($startDate)->endOfDay();
    } else {
        // إذا كانت فترة، نستخدم من بداية اليوم الأول إلى نهاية اليوم الأخير
        $startDateTime = Carbon::parse($startDate)->startOfDay();
        $endDateTime = Carbon::parse($endDate)->endOfDay();
    }

    // البحث عن سجلات الحضور في الفترة المحددة
    $attendances = $this->attendanceRecords()
        ->whereBetween('checkin_time', [$startDateTime, $endDateTime])
        ->whereNotNull('checkout_time')
        ->get();

    $totalHours = 0;

    foreach ($attendances as $attendance) {
        $checkinTime = Carbon::parse($attendance->checkin_time);
        $checkoutTime = Carbon::parse($attendance->checkout_time);

        // التأكد من أن وقت الانصراف لا يتجاوز نهاية الفترة
        if ($checkoutTime > $endDateTime) {
            $checkoutTime = $endDateTime;
        }

        $totalHours += $checkinTime->diffInHours($checkoutTime, true);
    }

    return $totalHours * $this->hourly_rate;
}
```

### 3. تحديث SalesReport.vue

#### إضافة totalSalaries إلى props:
```javascript
props: {
    // ... props الأخرى
    totalSalaries: Number,
    // ...
},
```

#### إضافة قسم إجمالي الرواتب في الواجهة:
```html
<!-- إجمالي الرواتب مع رابط -->
<div v-if="sales.length > 0" class="mt-2 text-lg font-bold text-center bg-orange-100 p-3 rounded-lg cursor-pointer hover:bg-orange-200 transition-colors" @click="goToEmployees">
  👥 إجمالي الرواتب: {{ formatPrice(totalSalaries) }}
  <span class="text-sm text-blue-600 block mt-1">
    اضغط هنا لعرض تفاصيل الموظفين 
    <span v-if="getSelectedDateText()" class="text-gray-600">
      ({{ getSelectedDateText() }})
    </span>
  </span>
</div>
```

#### إضافة دالة goToEmployees:
```javascript
// دالة الانتقال لصفحة الموظفين مع التاريخ المحدد
goToEmployees() {
  // الانتقال إلى صفحة الموظفين (لا تحتاج لمعاملات تاريخ لأنها تعرض اليوم الحالي)
  Inertia.get(route('admin.employees.index'));
},
```

## الميزات الجديدة

### 1. عرض إجمالي الرواتب
- يتم عرض إجمالي الرواتب لجميع الموظفين النشطين في الفترة المحددة
- يتم حساب الرواتب بناءً على ساعات العمل الفعلية المسجلة

### 2. رابط سريع لصفحة الموظفين
- عند النقر على قسم إجمالي الرواتب، يتم الانتقال إلى صفحة إدارة الموظفين
- يمكن للمستخدم مراجعة تفاصيل الحضور والانصراف للموظفين

### 3. مرونة في الفترات الزمنية
- يدعم حساب الرواتب ليوم واحد أو فترة زمنية
- يستخدم منطق مرن يعتمد على بداية ونهاية اليوم بدلاً من الساعة 7 صباحاً

## اختبار التحديث

### اختبار حساب الرواتب:
```bash
# اختبار حساب راتب موظف واحد
Employee: طه, Salary for today: 1.3111111111111

# اختبار إجمالي الرواتب لجميع الموظفين
Total salaries for all employees: 1.625
```

## الملفات المحدثة

1. **`app/Http/Controllers/Admin/SalesReportController.php`**
   - إضافة حساب إجمالي الرواتب
   - إضافة totalSalaries إلى البيانات المرسلة

2. **`app/Models/Employee.php`**
   - إضافة دالة getTotalAmountForPeriod

3. **`resources/js/Pages/Admin/SalesReport.vue`**
   - إضافة totalSalaries إلى props
   - إضافة قسم إجمالي الرواتب في الواجهة
   - إضافة دالة goToEmployees

## الخلاصة

تم إضافة إجمالي الرواتب بنجاح إلى صفحة تقرير المبيعات. الآن يمكن للمستخدمين:

- ✅ رؤية إجمالي الرواتب في تقرير المبيعات
- ✅ الانتقال السريع إلى صفحة الموظفين
- ✅ مراجعة تفاصيل الحضور والانصراف
- ✅ حساب الرواتب لفترات زمنية مختلفة

التحديث يوفر رؤية شاملة للمصروفات والرواتب في مكان واحد، مما يسهل عملية التحليل المالي. 