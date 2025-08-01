# نظام تحليل تكلفة المنتجات 🎯

## نظرة عامة

تم تطوير نظام متكامل لحساب تكلفة المواد الخام لكل منتج، مما يتيح لك معرفة هامش الربح بدقة لكل منتج.

## المميزات الرئيسية

### 1. تسعير المواد الخام المرن
- **الوحدة الأساسية**: حدد وحدة أساسية (مثل: لتر، كجم) وسعرها
- **معاملات التحويل**: أضف معاملات تحويل للوحدات الأخرى (مثل: مللي = 0.001 لتر)
- **حساب تلقائي**: النظام يحسب تلقائياً سعر أي وحدة بناءً على الوحدة الأساسية

### 2. مثال عملي: صولو بلوبري

```
الوحدة الأساسية: لتر
سعر الوحدة الأساسية: 50 ريال للتر
معاملات التحويل:
- مللي: 0.001 (1 مللي = 0.001 لتر)
- سنتي لتر: 0.01 (1 سنتي لتر = 0.01 لتر)
```

**النتيجة**:
- سعر المللي = 50 × 0.001 = 0.05 ريال
- سعر السنتي لتر = 50 × 0.01 = 0.5 ريال

### 3. تحليل تكلفة المنتجات

#### صفحة تحليل التكلفة الجديدة
- عرض تكلفة المواد الخام لكل منتج
- حساب هامش الربح بالريال والنسبة المئوية
- تفاصيل كل مكون مع سعر الوحدة
- ملخص عام لجميع المنتجات

#### المعلومات المعروضة:
- **سعر البيع**: السعر الذي يدفعه العميل
- **تكلفة المواد الخام**: إجمالي تكلفة المكونات
- **هامش الربح**: الفرق بين سعر البيع والتكلفة
- **نسبة الربح**: النسبة المئوية للربح

## كيفية الاستخدام

### الخطوة 1: إضافة المواد الخام مع التسعير

1. اذهب إلى **المواد الخام** → **إضافة مادة خام جديدة**
2. املأ المعلومات الأساسية (الاسم، الوحدة، المخزون)
3. في قسم **معلومات التسعير**:
   - أدخل الوحدة الأساسية (مثل: لتر)
   - أدخل سعر الوحدة الأساسية (مثل: 50 ريال)
   - أضف معاملات التحويل للوحدات الأخرى

### الخطوة 2: إضافة المنتجات مع المكونات

1. اذهب إلى **المنتجات** → **إضافة منتج جديد**
2. حدد المكونات لكل حجم من المنتج
3. النظام سيحسب تلقائياً تكلفة كل مكون

### الخطوة 3: تحليل التكلفة

1. اذهب إلى **المنتجات** → **💰 تحليل التكلفة**
2. شاهد تفاصيل تكلفة كل منتج
3. استخدم **عرض التفاصيل** لرؤية تكلفة كل مكون

## أمثلة عملية

### مثال 1: عصير برتقال

**المواد الخام**:
- برتقال: 10 ريال/كجم
- سكر: 5 ريال/كجم
- ماء: 2 ريال/لتر

**المنتج**:
- عصير برتقال وسط (500 مل)
- المكونات: 200 جرام برتقال + 50 جرام سكر + 250 مل ماء
- سعر البيع: 15 ريال

**الحساب**:
- تكلفة البرتقال: 0.2 × 10 = 2 ريال
- تكلفة السكر: 0.05 × 5 = 0.25 ريال
- تكلفة الماء: 0.25 × 2 = 0.5 ريال
- **إجمالي التكلفة**: 2.75 ريال
- **هامش الربح**: 15 - 2.75 = 12.25 ريال
- **نسبة الربح**: (12.25 ÷ 15) × 100 = 81.7%

### مثال 2: قهوة أمريكية

**المواد الخام**:
- قهوة مطحونة: 80 ريال/كجم
- ماء: 2 ريال/لتر

**المنتج**:
- قهوة أمريكية كبيرة (400 مل)
- المكونات: 20 جرام قهوة + 380 مل ماء
- سعر البيع: 12 ريال

**الحساب**:
- تكلفة القهوة: 0.02 × 80 = 1.6 ريال
- تكلفة الماء: 0.38 × 2 = 0.76 ريال
- **إجمالي التكلفة**: 2.36 ريال
- **هامش الربح**: 12 - 2.36 = 9.64 ريال
- **نسبة الربح**: (9.64 ÷ 12) × 100 = 80.3%

## الفوائد

### 1. دقة في التسعير
- معرفة التكلفة الحقيقية لكل منتج
- تحديد هامش ربح مناسب
- تجنب الخسائر من تسعير خاطئ

### 2. تحسين الربحية
- تحديد المنتجات الأكثر ربحية
- تحسين المكونات لزيادة الربح
- إدارة المخزون بكفاءة

### 3. اتخاذ قرارات مدروسة
- مقارنة ربحية المنتجات
- تحديد أسعار مناسبة
- تحسين المزيج المنتج

## نصائح للاستخدام

### 1. تحديث الأسعار بانتظام
- راجع أسعار المواد الخام شهرياً
- حدث معاملات التحويل عند الحاجة
- تتبع تغيرات الأسعار

### 2. تحليل الربحية
- راجع صفحة تحليل التكلفة أسبوعياً
- حدد المنتجات منخفضة الربحية
- فكر في تحسين المكونات أو الأسعار

### 3. إدارة المخزون
- استخدم حد التنبيه للمواد الخام
- تتبع استهلاك المواد
- خطط للمشتريات بناءً على الاستهلاك

## الدعم التقني

إذا واجهت أي مشاكل أو لديك استفسارات:
1. راجع هذا الدليل أولاً
2. تأكد من إدخال البيانات بشكل صحيح
3. تحقق من معاملات التحويل
4. اتصل بالدعم الفني إذا لزم الأمر

---

**ملاحظة**: هذا النظام مصمم لمساعدتك في اتخاذ قرارات تجارية مدروسة وتحسين ربحية عملك. استخدمه بانتظام للحصول على أفضل النتائج. 