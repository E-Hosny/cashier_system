<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير جرد #{{ $inventoryCount->id }}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            direction: rtl;
            font-size: 11px;
            color: #111;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 8px;
        }
        .header h1 {
            margin: 0 0 4px;
            font-size: 18px;
        }
        .header .meta {
            color: #475569;
            font-size: 10px;
            line-height: 1.6;
        }
        .summary {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 14px;
        }
        .summary td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            width: 25%;
            vertical-align: top;
        }
        .summary .label {
            color: #64748b;
            font-size: 9px;
            display: block;
            margin-bottom: 2px;
        }
        .summary .value {
            font-weight: bold;
            font-size: 12px;
        }
        .surplus { color: #15803d; }
        .shortage { color: #b91c1c; }
        table.items {
            width: 100%;
            border-collapse: collapse;
        }
        table.items th {
            background: #1e293b;
            color: #fff;
            padding: 6px 4px;
            font-size: 9px;
            border: 1px solid #0f172a;
            text-align: center;
        }
        table.items td {
            border: 1px solid #cbd5e1;
            padding: 5px 4px;
            text-align: center;
            font-size: 9px;
        }
        table.items td.name {
            text-align: right;
            font-weight: bold;
        }
        table.items tr:nth-child(even) td {
            background: #f8fafc;
        }
        .pos { color: #15803d; font-weight: bold; }
        .neg { color: #b91c1c; font-weight: bold; }
        .footer {
            margin-top: 12px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
        .notes {
            margin-top: 8px;
            padding: 6px 8px;
            border: 1px dashed #94a3b8;
            background: #f8fafc;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير جرد المواد الخام</h1>
        <div class="meta">
            فرع: <strong>{{ $inventoryCount->branch?->name }}</strong>
            &nbsp;|&nbsp; رقم الجرد: #{{ $inventoryCount->id }}
            &nbsp;|&nbsp; الحالة: {{ $statusLabel }}
            <br>
            البدء: {{ optional($inventoryCount->started_at)?->format('Y-m-d H:i') }}
            @if($inventoryCount->starter)
                ({{ $inventoryCount->starter->name }})
            @endif
            @if($inventoryCount->completed_at)
                &nbsp;|&nbsp; الإنهاء: {{ $inventoryCount->completed_at->format('Y-m-d H:i') }}
                @if($inventoryCount->completer)
                    ({{ $inventoryCount->completer->name }})
                @endif
            @endif
        </div>
    </div>

    <table class="summary">
        <tr>
            <td>
                <span class="label">عدد الأصناف</span>
                <span class="value">{{ $inventoryCount->counted_items_count }} / {{ $inventoryCount->items_count }}</span>
            </td>
            <td>
                <span class="label">إجمالي العجز (قيمة)</span>
                <span class="value shortage">{{ number_format((float) $inventoryCount->total_shortage_value, 2) }}</span>
            </td>
            <td>
                <span class="label">إجمالي الزيادة (قيمة)</span>
                <span class="value surplus">{{ number_format((float) $inventoryCount->total_surplus_value, 2) }}</span>
            </td>
            <td>
                <span class="label">صافي فرق القيمة</span>
                <span class="value {{ (float) $inventoryCount->net_diff_value >= 0 ? 'surplus' : 'shortage' }}">
                    {{ number_format((float) $inventoryCount->net_diff_value, 2) }}
                </span>
            </td>
        </tr>
    </table>

    @if($inventoryCount->notes)
        <div class="notes"><strong>ملاحظات:</strong> {{ $inventoryCount->notes }}</div>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 22%;">المادة</th>
                <th style="width: 12%;">الفئة</th>
                <th style="width: 10%;">رصيد النظام</th>
                <th style="width: 10%;">الفعلي</th>
                <th style="width: 10%;">فرق الكمية</th>
                <th style="width: 11%;">قيمة النظام</th>
                <th style="width: 11%;">فرق القيمة</th>
                <th style="width: 10%;">الحالة</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="name">{{ $row['product_name'] }}</td>
                    <td>{{ $row['category_name'] ?: '—' }}</td>
                    <td>{{ number_format($row['system_pieces'], 2) }}</td>
                    <td>{{ $row['counted_pieces'] !== null ? number_format($row['counted_pieces'], 2) : '—' }}</td>
                    <td class="{{ $row['diff_pieces'] > 0.0001 ? 'pos' : ($row['diff_pieces'] < -0.0001 ? 'neg' : '') }}">
                        @if($row['diff_pieces'] !== null)
                            {{ $row['diff_pieces'] > 0 ? '+' : '' }}{{ number_format($row['diff_pieces'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ number_format($row['system_value'], 2) }}</td>
                    <td class="{{ $row['diff_value'] > 0.009 ? 'pos' : ($row['diff_value'] < -0.009 ? 'neg' : '') }}">
                        @if($row['diff_value'] !== null)
                            {{ $row['diff_value'] > 0 ? '+' : '' }}{{ number_format($row['diff_value'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row['is_counted'] ? 'معدود' : 'غير معدود' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">لا توجد أصناف</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        تم التحميل بتاريخ {{ now()->format('Y-m-d H:i') }} — تقرير جرد رقم {{ $inventoryCount->id }}
    </div>
</body>
</html>
