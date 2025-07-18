# نظام إدارة الموظفين والرواتب - التحديث الجديد

## نظرة عامة
تم تحديث نظام إدارة الموظفين ليعمل بنفس منطق نظام المبيعات، حيث يتم حساب الرواتب من الساعة 7:00 صباحاً إلى الساعة 7:00 صباحاً للوم التالي.

## الميزات الجديدة

### 1. منطق الفترة الزمنية (7 صباحاً - 7 صباحاً)
- **قبل الساعة 7 صباحاً**: يعرض الرواتب من 7 صباحاً اليوم السابق إلى 7 صباحاً اليوم الحالي
- **بعد الساعة 7 صباحاً**: يعرض الرواتب من 7 صباحاً اليوم الحالي إلى 7 صباحاً للوم التالي

### 2. عرض الرواتب المحدث
- **رواتب كل موظف**: يعرض راتب كل موظف للفترة الحالية
- **إجمالي الرواتب**: يعرض إجمالي رواتب جميع الموظفين
- **إجمالي الساعات**: يعرض إجمالي ساعات العمل لجميع الموظفين
- **الفترة الزمنية**: يعرض الفترة الزمنية الحالية بوضوح

### 3. تحسينات الواجهة
- عرض الفترة الزمنية الحالية في أعلى الصفحة
- إحصائيات شاملة تشمل إجمالي الرواتب والساعات
- تصميم محسن مع ألوان مميزة لكل نوع من المعلومات

## التحديثات التقنية

### 1. نموذج Employee
```php
// دالة حساب الساعات المحدثة
public function getTodayHours()
{
    $now = Carbon::now();
    $currentHour = $now->hour;
    
    if ($currentHour < 7) {
        // قبل الساعة 7 صباحاً
        $startDate = $now->copy()->subDay()->setTime(7, 0, 0);
        $endDate = $now->copy()->setTime(7, 0, 0);
    } else {
        // بعد الساعة 7 صباحاً
        $startDate = $now->copy()->setTime(7, 0, 0);
        $endDate = $now->copy()->addDay()->setTime(7, 0, 0);
    }
    
    // حساب الساعات من سجلات الحضور في الفترة المحددة
    $attendances = $this->attendanceRecords()
        ->whereBetween('checkin_time', [$startDate, $endDate])
        ->get();
    
    $totalHours = 0;
    foreach ($attendances as $attendance) {
        $checkinTime = Carbon::parse($attendance->checkin_time);
        $checkoutTime = $attendance->checkout_time ?? Carbon::now();
        $checkoutTime = Carbon::parse($checkoutTime);
        
        if ($checkoutTime > $endDate) {
            $checkoutTime = $endDate;
        }
        
        $totalHours += $checkinTime->diffInHours($checkoutTime, true);
    }
    
    return $totalHours;
}

// دالة الحصول على نص الفترة الزمنية
public function getCurrentPeriodText()
{
    $now = Carbon::now();
    $currentHour = $now->hour;
    
    if ($currentHour < 7) {
        $startDate = $now->copy()->subDay()->format('Y-m-d');
        $endDate = $now->copy()->format('Y-m-d');
        return "من الساعة 7:00 صباحاً {$startDate} إلى الساعة 7:00 صباحاً {$endDate}";
    } else {
        $startDate = $now->copy()->format('Y-m-d');
        $endDate = $now->copy()->addDay()->format('Y-m-d');
        return "من الساعة 7:00 صباحاً {$startDate} إلى الساعة 7:00 صباحاً {$endDate}";
    }
}
```

### 2. الكنترولر المحدث
```php
public function index()
{
    $employees = Employee::where('is_active', true)->get();

    // إضافة معلومات الحضور الحالية لكل موظف
    $employees->each(function ($employee) {
        $employee->current_attendance = $employee->getCurrentAttendance();
        $employee->is_present = $employee->isCurrentlyPresent();
        $employee->today_hours = $employee->getTodayHours();
        $employee->today_amount = $employee->getTodayAmount();
    });

    // حساب إجمالي الرواتب لليوم الحالي
    $totalTodayAmount = $employees->sum('today_amount');
    $totalTodayHours = $employees->sum('today_hours');
    $currentPeriodText = $employees->first() ? $employees->first()->getCurrentPeriodText() : '';

    return Inertia::render('Admin/Employees/Index', [
        'employees' => $employees,
        'totalTodayAmount' => $totalTodayAmount,
        'totalTodayHours' => $totalTodayHours,
        'currentPeriodText' => $currentPeriodText,
    ]);
}
```

### 3. الواجهة الأمامية المحدثة
```vue
<!-- عرض الفترة الزمنية الحالية -->
<div class="bg-indigo-50 p-4 rounded-lg mb-6">
  <div class="text-indigo-800 text-sm font-medium">⏰ الفترة الزمنية الحالية:</div>
  <div class="text-indigo-600 text-lg">{{ currentPeriodText }}</div>
</div>

<!-- إحصائيات شاملة -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
  <!-- إجمالي الموظفين -->
  <div class="bg-blue-50 p-4 rounded-lg">
    <div class="text-blue-600 text-2xl font-bold">{{ employees.length }}</div>
    <div class="text-blue-800 text-sm">إجمالي الموظفين</div>
  </div>
  
  <!-- الموظفين الحاضرين -->
  <div class="bg-green-50 p-4 rounded-lg">
    <div class="text-green-600 text-2xl font-bold">{{ presentEmployees.length }}</div>
    <div class="text-green-800 text-sm">الموظفين الحاضرين</div>
  </div>
  
  <!-- الموظفين الغائبين -->
  <div class="bg-yellow-50 p-4 rounded-lg">
    <div class="text-yellow-600 text-2xl font-bold">{{ absentEmployees.length }}</div>
    <div class="text-yellow-800 text-sm">الموظفين الغائبين</div>
  </div>
  
  <!-- إجمالي الرواتب -->
  <div class="bg-purple-50 p-4 rounded-lg">
    <div class="text-purple-600 text-2xl font-bold">{{ formatPrice(totalTodayAmount) }}</div>
    <div class="text-purple-800 text-sm">إجمالي الرواتب اليوم</div>
  </div>
  
  <!-- إجمالي الساعات -->
  <div class="bg-orange-50 p-4 rounded-lg">
    <div class="text-orange-600 text-2xl font-bold">{{ totalTodayHours.toFixed(2) }}</div>
    <div class="text-orange-800 text-sm">إجمالي الساعات اليوم</div>
  </div>
</div>
```

## كيفية الاستخدام

### 1. عرض الرواتب
- انتقل إلى صفحة "الموظفين" من لوحة التحكم
- ستظهر الفترة الزمنية الحالية في أعلى الصفحة
- ستجد إحصائيات شاملة تشمل إجمالي الرواتب والساعات

### 2. مراقبة الرواتب
- **قبل الساعة 7 صباحاً**: يعرض الرواتب من اليوم السابق
- **بعد الساعة 7 صباحاً**: يعرض الرواتب من اليوم الحالي
- يتم تحديث الحسابات تلقائياً عند تسجيل الحضور والانصراف

### 3. إدارة الموظفين
- تسجيل الحضور والانصراف كالمعتاد
- عرض راتب كل موظف في العمود المخصص
- مراقبة إجمالي الرواتب في الإحصائيات العلوية

## المزايا

### 1. دقة في الحسابات
- حساب دقيق للساعات بناءً على الفترة الزمنية المحددة
- معالجة حالات الانصراف المتأخر
- حساب صحيح للرواتب حسب سعر الساعة

### 2. وضوح في العرض
- عرض الفترة الزمنية بوضوح
- إحصائيات شاملة ومفصلة
- تصميم منظم وسهل القراءة

### 3. اتساق مع النظام
- نفس منطق نظام المبيعات
- تجربة مستخدم موحدة
- سهولة في الفهم والاستخدام

## ملاحظات تقنية

### 1. معالجة التواريخ
- استخدام `Carbon::copy()` لتجنب تعديل الكائن الأصلي
- معالجة صحيحة للفترات الزمنية
- حساب دقيق للساعات والدقائق

### 2. الأداء
- استعلامات محسنة لقاعدة البيانات
- تحميل البيانات المطلوبة فقط
- تحديث فوري للواجهة

### 3. الأمان
- التحقق من صلاحيات المستخدم
- معالجة آمنة للبيانات
- حماية من الأخطاء الشائعة

## الخلاصة

تم تحديث نظام إدارة الموظفين بنجاح ليعمل بنفس منطق نظام المبيعات، مما يوفر:
- حساب دقيق للرواتب من 7 صباحاً إلى 7 صباحاً للوم التالي
- عرض واضح للفترة الزمنية الحالية
- إحصائيات شاملة تشمل إجمالي الرواتب والساعات
- واجهة محسنة وسهلة الاستخدام

النظام الآن جاهز للاستخدام ويوفر تجربة مستخدم ممتازة مع دقة عالية في الحسابات. 