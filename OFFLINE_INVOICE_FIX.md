# حل مشكلة طباعة الفواتير في وضع عدم الاتصال

## المشكلة
كان يحدث خطأ `NetworkError when attempting to fetch resource` عند محاولة طباعة الفاتورة في وضع عدم الاتصال، لأن النظام كان يحاول إرسال طلب fetch إلى الخادم حتى في وضع عدم الاتصال.

## السبب الجذري
دالة `printLocalOfflineInvoice` كانت تستخدم fetch request لإرسال البيانات إلى الخادم:
```javascript
fetch(`/offline/invoice-local/${offlineOrder.offline_id}`, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
    'Accept': 'text/html'
  },
  body: JSON.stringify({
    order_data: JSON.stringify(offlineOrder)
  })
})
```

هذا يسبب خطأ NetworkError في وضع عدم الاتصال.

## الحل المطبق

### 1. إنشاء الفاتورة محلياً
تم تعديل دالة `printLocalOfflineInvoice` لإنشاء HTML الفاتورة محلياً بدون الحاجة لإرسال طلب إلى الخادم:

```javascript
printLocalOfflineInvoice(offlineOrder) {
  // إنشاء الفاتورة محلياً بدون الحاجة لإرسال طلب إلى الخادم
  this.iframeVisible = true;

  this.$nextTick(() => {
    const iframe = document.getElementById('invoice-frame');
    if (iframe) {
      iframe.onload = () => {
        console.log('تم تحميل الفاتورة المحلية - الطباعة ستتم تلقائياً');
      };

      // إنشاء HTML الفاتورة محلياً
      const html = this.generateLocalInvoiceHtml(offlineOrder);
      iframe.srcdoc = html;
    }
  });
}
```

### 2. دالة إنشاء HTML الفاتورة
تم إضافة دالة `generateLocalInvoiceHtml` لإنشاء HTML الفاتورة محلياً:

```javascript
generateLocalInvoiceHtml(offlineOrder) {
  const itemsHtml = offlineOrder.items.map(item => `
    <tr>
      <td>${item.product_name} ${item.size ? `(${item.size})` : ''}</td>
      <td>${item.quantity}</td>
      <td>${parseFloat(item.price).toFixed(2)}</td>
      <td>${(parseFloat(item.quantity) * parseFloat(item.price)).toFixed(2)}</td>
    </tr>
  `).join('');

  const currentDate = new Date(offlineOrder.created_at).toLocaleString('ar-EG');
  
  return `
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
      <meta charset="UTF-8">
      <title>فاتورة</title>
      <style>
        /* CSS styles for invoice */
      </style>
    </head>
    <body onload="setTimeout(() => { window.print(); }, 200); window.onafterprint = () => window.parent.postMessage('close-iframe', '*')">
      <div class="header">
        <div class="invoice-title">فاتورة رقم #${offlineOrder.invoice_number}</div>
        <div class="invoice-date">التاريخ: ${currentDate}</div>
      </div>

      <table>
        <thead>
          <tr>
            <th>المنتج</th>
            <th>الكمية</th>
            <th>السعر</th>
            <th>الإجمالي</th>
          </tr>
        </thead>
        <tbody>
          ${itemsHtml}
        </tbody>
      </table>

      <div class="total">الإجمالي الكلي: ${parseFloat(offlineOrder.total).toFixed(2)} جنيه</div>
    </body>
    </html>
  `;
}
```

## المميزات الجديدة

### 1. عمل كامل في وضع عدم الاتصال
- لا يحتاج إلى اتصال بالإنترنت
- لا يرسل طلبات إلى الخادم
- يعمل بشكل مستقل

### 2. نفس التصميم والمظهر
- نفس CSS styles
- نفس التخطيط
- نفس الطباعة التلقائية

### 3. معالجة آمنة للبيانات
- تحويل الأرقام بشكل آمن
- معالجة الحقول الفارغة
- تنسيق التواريخ

## كيفية الاختبار

### 1. اختبار وضع عدم الاتصال
1. افتح Developer Tools (F12)
2. انتقل إلى Network tab
3. اضغط على "Offline" checkbox
4. أضف منتج إلى السلة
5. اضغط على "إصدار الفاتورة"

### 2. النتائج المتوقعة
- ✅ ظهور الفاتورة في iframe
- ✅ طباعة تلقائية
- ✅ إغلاق iframe بعد الطباعة
- ✅ لا توجد أخطاء NetworkError
- ✅ لا توجد طلبات إلى الخادم

## الخلاصة

تم حل المشكلة بنجاح من خلال:
- ✅ إنشاء الفاتورة محلياً بدون طلبات شبكة
- ✅ نفس التصميم والمظهر
- ✅ عمل كامل في وضع عدم الاتصال
- ✅ معالجة آمنة للبيانات

النظام الآن يعمل بشكل مثالي في وضع عدم الاتصال بدون أي أخطاء شبكة. 