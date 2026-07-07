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
            font-size: 16px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
            font-size: 15px;
        }
        th, td { 
            border: 1px solid #000; 
            padding: 10px; 
            text-align: right; 
        }
        th { 
            background: #eee; 
            font-weight: bold;
            font-size: 16px;
        }
        .total { 
            margin-top: 15px; 
            font-weight: bold; 
            font-size: 18px; 
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
            font-size: 22px;
            font-weight: bold;
            margin: 5px 0;
        }
        .invoice-date {
            font-size: 16px;
            color: #666;
        }
        .copy-badge {
            display: inline-block;
            margin-bottom: 8px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: bold;
            background: {{ ($copy ?? 'customer') === 'staff' ? '#fef3c7' : '#dbeafe' }};
            color: {{ ($copy ?? 'customer') === 'staff' ? '#92400e' : '#1e40af' }};
        }

        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body @if(empty($qzMode)) onload="setTimeout(() => { window.print(); }, 200); window.onafterprint = () => window.parent.postMessage('close-iframe', '*')" @endif>
    <div class="header">
        @if (!empty($logoUrl))
            <img src="{{ $logoUrl }}" alt="logo" class="logo">
        @endif
        <div class="copy-badge">{{ ($copy ?? 'customer') === 'staff' ? 'نسخة العامل' : 'نسخة الزبون' }}</div>
        <div class="invoice-title">فاتورة رقم #{{ $order->invoice_number ?? $order->id }}</div>
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
                @php
                    $size = is_array($item) ? ($item['size'] ?? '') : ($item->size ?? '');
                    $productName = is_array($item) ? $item['product_name'] : $item->product_name;
                    
                    if ($size === 'extra_large') {
                        $productName .= ' (كان كبير)';
                    }
                @endphp
                <tr>
                    <td>{{ $productName }}</td>
                    <td>{{ is_array($item) ? $item['quantity'] : $item->quantity }}</td>
                    <td>{{ number_format(is_array($item) ? $item['price'] : $item->price, 2) }}</td>
                    <td>{{ number_format((is_array($item) ? $item['quantity'] : $item->quantity) * (is_array($item) ? $item['price'] : $item->price), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">الإجمالي الكلي: {{ number_format($order->total, 2) }}</div>
</body>
</html>
