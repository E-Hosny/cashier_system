<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OfflineOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDuplicateInvoices extends Command
{
    protected $signature = 'invoices:check-duplicates';
    protected $description = 'فحص الفواتير المكررة في النظام';

    public function handle()
    {
        $this->info('=== فحص الفواتير المكررة ===');
        $this->newLine();

        // فحص الفواتير العادية
        $this->info('1. فحص الفواتير العادية:');
        $duplicates = Order::select('invoice_number', 'created_at', DB::raw('COUNT(*) as count'))
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number', 'created_at')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('لا توجد فواتير مكررة في الجدول العادي');
        } else {
            foreach ($duplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | التاريخ: {$dup->created_at} | العدد: {$dup->count}");
            }
        }

        $this->newLine();

        // فحص الفواتير الأوفلاين
        $this->info('2. فحص الفواتير الأوفلاين:');
        $offlineDuplicates = OfflineOrder::select('invoice_number', 'created_at', DB::raw('COUNT(*) as count'))
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number', 'created_at')
            ->having('count', '>', 1)
            ->get();

        if ($offlineDuplicates->isEmpty()) {
            $this->info('لا توجد فواتير مكررة في الجدول الأوفلاين');
        } else {
            foreach ($offlineDuplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | التاريخ: {$dup->created_at} | العدد: {$dup->count}");
            }
        }

        $this->newLine();

        // فحص جميع الفواتير
        $this->info('3 فحص جميع الفواتير (عادية + أوفلاين):');
        
        $allInvoices = collect();
        
        // جمع الفواتير العادية
        $orders = Order::select('invoice_number', 'created_at')
            ->whereNotNull('invoice_number')
            ->get();
        $allInvoices = $allInvoices->merge($orders);
        
        // جمع الفواتير الأوفلاين
        $offlineOrders = OfflineOrder::select('invoice_number', 'created_at')
            ->whereNotNull('invoice_number')
            ->get();
        $allInvoices = $allInvoices->merge($offlineOrders);
        
        // تجميع حسب رقم الفاتورة
        $groupedInvoices = $allInvoices->groupBy('invoice_number')
            ->filter(function ($group) {
                return $group->count() > 1;
            });

        if ($groupedInvoices->isEmpty()) {
            $this->info('لا توجد فواتير مكررة في النظام');
        } else {
            foreach ($groupedInvoices as $invoiceNumber => $orders) {
                $this->error("رقم الفاتورة: {$invoiceNumber} | العدد: {$orders->count()}");
                foreach ($orders as $order) {
                    $this->line("  - التاريخ: {$order->created_at}");
                }
            }
        }

        $this->newLine();

        // إحصائيات عامة
        $this->info('4.إحصائيات عامة:');      $totalOrders = Order::count();
        $totalOfflineOrders = OfflineOrder::count();
        $ordersWithInvoice = Order::whereNotNull('invoice_number')->count();
        $offlineOrdersWithInvoice = OfflineOrder::whereNotNull('invoice_number')->count();

        $this->line("إجمالي الطلبات العادية: {$totalOrders}");
        $this->line("إجمالي الطلبات الأوفلاين: {$totalOfflineOrders}");
        $this->line("الطلبات العادية مع أرقام فواتير: {$ordersWithInvoice}");
        $this->line("الطلبات الأوفلاين مع أرقام فواتير: {$offlineOrdersWithInvoice}");

        $this->newLine();
        $this->info('تم الانتهاء من الفحص');
    }
} 