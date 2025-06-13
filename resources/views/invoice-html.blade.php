<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة</title>
    <style>
        body { font-family: Arial, sans-serif; direction: rtl; padding: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: right; }
        th { background: #eee; }
        .total { margin-top: 20px; font-weight: bold; font-size: 1.2em; }
    </style>
</head>
<body onload="window.print(); window.onafterprint = () => window.parent.postMessage('close-iframe', '*')">
    <h2>فاتورة رقم #{{ $order->id }}</h2>
    <p>التاريخ: {{ $order->created_at->format('Y-m-d H:i') }}</p>

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
                    <td>{{ $item->product->name }}</td>
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
