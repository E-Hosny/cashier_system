<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OfflineOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\InvoiceNumberService;

class CheckDuplicateInvoices extends Command
{
    protected $signature = 'invoices:check-duplicates {--fix : إصلاح الفواتير المكررة تلقائياً}';
    protected $description = 'فحص وإصلاح الفواتير المكررة في النظام';

    public function handle()
    {
        $this->info('=== فحص الفواتير المكررة ===');
        $this->newLine();

        $shouldFix = $this->option('fix');
        
        // فحص الفواتير العادية
        $this->info('1. فحص الفواتير العادية:');
        $duplicates = Order::select('invoice_number', 'created_at', DB::raw('COUNT(*) as count'))
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number', 'created_at')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ لا توجد فواتير مكررة في الجدول العادي');
        } else {
            $this->warn("❌ تم العثور على " . $duplicates->count() . " فواتير مكررة:");
            foreach ($duplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | التاريخ: {$dup->created_at} | العدد: {$dup->count}");
            }
            
            if ($shouldFix) {
                $this->fixDuplicateOrders($duplicates);
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
            $this->info('✅ لا توجد فواتير مكررة في الجدول الأوفلاين');
        } else {
            $this->warn("❌ تم العثور على " . $offlineDuplicates->count() . " فواتير مكررة:");
            foreach ($offlineDuplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | التاريخ: {$dup->created_at} | العدد: {$dup->count}");
            }
            
            if ($shouldFix) {
                $this->fixDuplicateOfflineOrders($offlineDuplicates);
            }
        }

        $this->newLine();
        $this->info('=== انتهى الفحص ===');
    }
    
    /**
     * إصلاح الفواتير المكررة في جدول الطلبات العادية
     */
    private function fixDuplicateOrders($duplicates)
    {
        $this->info('🔧 إصلاح الفواتير المكررة في الجدول العادي...');
        
        foreach ($duplicates as $duplicate) {
            $orders = Order::where('invoice_number', $duplicate->invoice_number)
                ->orderBy('created_at', 'asc')
                ->get();
            
            // الاحتفاظ بالطلب الأول وتغيير باقي الطلبات
            $firstOrder = $orders->first();
            $otherOrders = $orders->skip(1);
            
            foreach ($otherOrders as $order) {
                $newInvoiceNumber = InvoiceNumberService::generateInvoiceNumber();
                $order->update(['invoice_number' => $newInvoiceNumber]);
                $this->info("تم تغيير رقم فاتورة الطلب {$order->id} إلى: {$newInvoiceNumber}");
            }
        }
        
        $this->info('✅ تم إصلاح الفواتير المكررة في الجدول العادي');
    }
    
    /**
     * إصلاح الفواتير المكررة في جدول الطلبات الأوفلاين
     */
    private function fixDuplicateOfflineOrders($duplicates)
    {
        $this->info('🔧 إصلاح الفواتير المكررة في الجدول الأوفلاين...');
        
        foreach ($duplicates as $duplicate) {
            $orders = OfflineOrder::where('invoice_number', $duplicate->invoice_number)
                ->orderBy('created_at', 'asc')
                ->get();
            
            // الاحتفاظ بالطلب الأول وتغيير باقي الطلبات
            $firstOrder = $orders->first();
            $otherOrders = $orders->skip(1);
            
            foreach ($otherOrders as $order) {
                $newInvoiceNumber = InvoiceNumberService::generateInvoiceNumber();
                $order->update(['invoice_number' => $newInvoiceNumber]);
                $this->info("تم تغيير رقم فاتورة الطلب الأوفلاين {$order->id} إلى: {$newInvoiceNumber}");
            }
        }
        
        $this->info('✅ تم إصلاح الفواتير المكررة في الجدول الأوفلاين');
    }
} 