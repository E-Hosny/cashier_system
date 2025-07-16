# تصحيح مشكلة نوع البيانات للسعر - Price Type Fix

## المشكلة المكتشفة

عند اختبار النظام في وضع Network Offline، ظهر خطأ:
```
TypeError: s.price.toFixed is not a function
```

## السبب الجذري

المشكلة كانت في أن قيم الأسعار تأتي من قاعدة البيانات كـ `string` وليس كـ `number`، مما يسبب خطأ عند محاولة استدعاء `toFixed()` عليها.

## الحل المطبق

### 1. تصحيح دالة `addToCart()`

#### ✅ تحويل السعر إلى رقم عند الإضافة للسلة
```javascript
// للمنتجات ذات الأحجام المختلفة
price: parseFloat(variant.price) || 0,

// للمنتجات بدون أحجام
price: parseFloat(product.price) || 0,
```

### 2. تصحيح دالة `checkout()`

#### ✅ تحويل جميع البيانات إلى الأرقام الصحيحة
```javascript
const checkoutData = {
  items: this.cart.map(item => ({
    product_id: item.product_id,
    product_name: item.name,
    quantity: parseInt(item.quantity) || 0,
    price: parseFloat(item.price) || 0,
    size: item.size
  })),
  total_price: parseFloat(this.totalAmount) || 0,
  payment_method: 'cash'
};
```

### 3. تصحيح دالة `generateLocalInvoiceHtml()`

#### ✅ معالجة آمنة للبيانات في الفاتورة
```javascript
const itemsHtml = offlineOrder.items.map(item => {
  // التأكد من أن السعر رقم
  const price = parseFloat(item.price) || 0;
  const quantity = parseInt(item.quantity) || 0;
  const total = price * quantity;
  
  return `
    <tr>
      <td>${item.product_name} ${item.size ? `(${item.size})` : ''}</td>
      <td>${quantity}</td>
      <td>${price.toFixed(2)} جنيه</td>
      <td>${total.toFixed(2)} جنيه</td>
    </tr>
  `;
}).join('');

// الإجمالي أيضاً
<div class="total">
  الإجمالي: ${(parseFloat(offlineOrder.total) || 0).toFixed(2)} جنيه
</div>
```

## الملفات المحدثة

### 1. `resources/js/Pages/Cashier.vue`
- ✅ تصحيح دالة `addToCart()` - تحويل الأسعار إلى أرقام
- ✅ تصحيح دالة `checkout()` - تحويل جميع البيانات
- ✅ تصحيح دالة `generateLocalInvoiceHtml()` - معالجة آمنة للبيانات

## كيفية الاختبار

### 1. اختبار Network Offline
1. افتح Developer Tools (F12)
2. انتقل إلى Network tab
3. اضغط على "Offline" checkbox
4. أضف منتج إلى السلة
5. اضغط على "إصدار الفاتورة"

### 2. النتائج المتوقعة
- ✅ **لا توجد أخطاء `TypeError`**
- ✅ **إنشاء طلب أوفلاين محلي بنجاح**
- ✅ **طباعة الفاتورة بدون مشاكل**
- ✅ **عرض الأسعار بشكل صحيح**

### 3. في Console
```
عدم الاتصال مكتشف - إنشاء طلب أوفلاين محلي...
سبب عدم الاتصال: ns_error_offline
تم إنشاء طلب أوفلاين محلي: {offline_id: "OFF_...", ...}
تم حفظ طلب أوفلاين محلي في localStorage
```

## المميزات الجديدة

### 1. معالجة آمنة للبيانات
- تحويل جميع الأسعار إلى أرقام
- تحويل جميع الكميات إلى أرقام صحيحة
- معالجة القيم الفارغة أو غير الصحيحة

### 2. توافق مع قاعدة البيانات
- يعمل مع البيانات المخزنة كـ string
- يعمل مع البيانات المخزنة كـ number
- معالجة القيم null أو undefined

### 3. تجربة مستخدم محسنة
- لا توجد أخطاء JavaScript
- عرض صحيح للأسعار
- طباعة فواتير بدون مشاكل

## ملاحظات مهمة

1. **الأمان**: استخدام `|| 0` يضمن عدم وجود أخطاء
2. **التوافق**: يعمل مع جميع أنواع البيانات
3. **الأداء**: التحويل يتم مرة واحدة فقط
4. **الموثوقية**: معالجة شاملة لجميع الحالات

## استكشاف الأخطاء

إذا استمرت المشكلة:

1. **تحقق من سجلات Console** - ابحث عن أخطاء `TypeError`
2. **راجع بيانات المنتجات** - تأكد من صحة الأسعار في قاعدة البيانات
3. **اختبر في متصفح مختلف** - للتأكد من عدم وجود مشاكل في الكاش

## الخلاصة

هذا التصحيح يحل مشكلة `TypeError: s.price.toFixed is not a function` بشكل نهائي ويضمن عمل النظام بشكل صحيح مع جميع أنواع البيانات، سواء كانت مخزنة كـ string أو number في قاعدة البيانات. 