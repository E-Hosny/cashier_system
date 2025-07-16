<?php

// Script لتحديث الطلبات القديمة
// تشغيل هذا في Tinker: php artisan tinker

use App\Models\Order;
use App\Models\CashierShift;

// تحديث الطلبات التي لها cashier_shift_id
$ordersWithShift = Order::whereNotNull('cashier_shift_id')
    ->whereNull('user_id')
    ->with('cashierShift')
    ->get();

foreach ($ordersWithShift as $order) {
    if ($order->cashierShift && $order->cashierShift->user_id) {
        $order->update(['user_id' => $order->cashierShift->user_id]);
        echo "تم تحديث الطلب رقم {$order->id} - المستخدم: {$order->cashierShift->user_id}\n";
    }
}

// تحديث الطلبات التي لها tenant_id
$ordersWithTenant = Order::whereNotNull('tenant_id')
    ->whereNull('user_id')
    ->get();

foreach ($ordersWithTenant as $order) {
    $order->update(['user_id' => $order->tenant_id]);
    echo "تم تحديث الطلب رقم {$order->id} - المستخدم: {$order->tenant_id}\n";
}

echo "تم الانتهاء من تحديث الطلبات القديمة!\n"; 