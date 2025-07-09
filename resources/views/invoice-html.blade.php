<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            direction: rtl; 
            padding: 10px; 
            margin: 0;
            font-size: 12px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            font-size: 11px;
        }
        th, td { 
            border: 1px solid #000; 
            padding: 6px; 
            text-align: right; 
        }
        th { 
            background: #eee; 
            font-weight: bold;
        }
        .total { 
            margin-top: 15px; 
            font-weight: bold; 
            font-size: 14px; 
            text-align: center;
        }
        .logo {
            width: 150px;
            height: auto;
            display: block;
            margin: 0 auto 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .invoice-title {
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }
        .invoice-date {
            font-size: 11px;
            color: #666;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="setTimeout(() => { window.print(); }, 200); window.onafterprint = () => window.parent.postMessage('close-iframe', '*')">
    <div class="header">
        <img src="{{ url('/images/mylogo.png') }}" alt="logo" class="logo">
        <div class="invoice-title">فاتورة رقم #{{ $order->id }}</div>
        <div class="invoice-date">التاريخ: {{ $order->created_at->format('Y-m-d H:i') }}</div>
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
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }} ({{ $item->size }})</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price, 2) }}</td>
                    <td>{{ number_format($item->quantity * $item->price, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">الإجمالي الكلي: {{ number_format($order->total, 2) }}</div>
    

</body>
</html>
