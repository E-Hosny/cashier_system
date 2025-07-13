<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>فاتورة</title>
    <style>
        @font-face {
            font-family: 'arial';
            src: url("{{ public_path('fonts/arial.ttf') }}") format('truetype');
        }
        
        @font-face {
            font-family: 'arial';
            font-weight: bold;
            src: url("{{ public_path('fonts/arial.ttf') }}") format('truetype');
        }

        body {
            font-family: 'arial', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 15px;
        }

        th, td {
            border: 1px solid black;
            padding: 12px;
            text-align: right;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 16px;
        }
        
        .total {
            font-weight: bold;
            font-size: 18px;
            margin-top: 20px;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        p {
            font-size: 16px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="{{ public_path('images/mylogo.png') }}" alt="logo" style="width: 200px;">
    </div>
    <h1>فاتورة رقم #{{ $order->invoice_number ?? $order->id }}</h1>
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
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }} ({{ $item->size }})</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->price, 2, '.', ',') }}</td>
                    <td>{{ number_format($item->price * $item->quantity, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">الإجمالي الكلي: {{ number_format($order->total, 2, '.', ',') }}</div>
</body>
</html>
