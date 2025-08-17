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

    /**
     * فحص شامل للفواتير المكررة
     */
    public function handle()
    {
        $this->info('=== فحص شامل للفواتير المكررة ===');
        $this->newLine();

        $shouldFix = $this->option('fix');
        
        // فحص الفواتير العادية
        $this->info('1. فحص الفواتير العادية:');
        $duplicates = Order::select('invoice_number', 'created_at', DB::raw('COUNT(*) as count'))
            ->whereNotNull('invoice_number')
            ->groupBy('invoice_number')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('✅ لا توجد فواتير مكررة في الجدول العادي');
        } else {
            $this->warn("❌ تم العثور على " . $duplicates->count() . " فواتير مكررة:");
            foreach ($duplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | العدد: {$dup->count}");
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
            ->groupBy('invoice_number')
            ->having('count', '>', 1)
            ->get();

        if ($offlineDuplicates->isEmpty()) {
            $this->info('✅ لا توجد فواتير مكررة في الجدول الأوفلاين');
        } else {
            $this->warn("❌ تم العثور على " . $offlineDuplicates->count() . " فواتير مكررة:");
            foreach ($offlineDuplicates as $dup) {
                $this->warn("رقم الفاتورة: {$dup->invoice_number} | العدد: {$dup->count}");
            }
            
            if ($shouldFix) {
                $this->fixDuplicateOfflineOrders($offlineDuplicates);
            }
        }

        $this->newLine();

        // فحص الطلبات المتطابقة تماماً (نفس المحتوى والتوقيت)
        $this->info('3. فحص الطلبات المتطابقة تماماً:');
        $this->checkDuplicateContentOrders($shouldFix);

        $this->newLine();

        // فحص تضارب الأرقام بين الجدولين
        $this->info('4. فحص تضارب الأرقام بين الجدولين:');
        $this->checkCrossTableConflicts($shouldFix);

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

    /**
     * فحص الطلبات المتطابقة تماماً
     */
    private function checkDuplicateContentOrders($shouldFix)
    {
        // البحث عن طلبات متطابقة في المحتوى والتوقيت
        $duplicateContent = DB::select("
            SELECT 
                o1.id as order1_id,
                o2.id as order2_id,
                o1.invoice_number as invoice1,
                o2.invoice_number as invoice2,
                o1.total,
                o1.created_at,
                COUNT(oi1.id) as items_count
            FROM orders o1
            JOIN orders o2 ON o1.id != o2.id 
                AND o1.total = o2.total 
                AND ABS(TIMESTAMPDIFF(SECOND, o1.created_at, o2.created_at)) <= 30
                AND o1.user_id = o2.user_id
            JOIN order_items oi1 ON o1.id = oi1.order_id
            JOIN order_items oi2 ON o2.id = oi2.order_id
            WHERE oi1.product_name = oi2.product_name 
                AND oi1.quantity = oi2.quantity 
                AND oi1.price = oi2.price
            GROUP BY o1.id, o2.id, o1.total, o1.created_at
            HAVING COUNT(oi1.id) = (
                SELECT COUNT(*) FROM order_items WHERE order_id = o1.id
            )
        ");

        if (empty($duplicateContent)) {
            $this->info('✅ لا توجد طلبات متطابقة تماماً في المحتوى');
        } else {
            $this->warn("❌ تم العثور على " . count($duplicateContent) . " مجموعة طلبات متطابقة تماماً:");
            
            foreach ($duplicateContent as $dup) {
                $this->warn("   - الطلب {$dup->order1_id} (فاتورة: {$dup->invoice1})");
                $this->warn("   - الطلب {$dup->order2_id} (فاتورة: {$dup->invoice2})");
                $this->warn("   - المبلغ: {$dup->total} | العناصر: {$dup->items_count}");
                $this->newLine();
            }
            
            if ($shouldFix) {
                $this->fixDuplicateContentOrders($duplicateContent);
            }
        }
    }

    /**
     * فحص تضارب الأرقام بين الجدولين
     */
    private function checkCrossTableConflicts($shouldFix)
    {
        // البحث عن تضارب في أرقام الفواتير بين الجدولين
        $crossTableConflicts = DB::select("
            SELECT 
                o.invoice_number,
                o.id as order_id,
                'orders' as table_name,
                o.created_at
            FROM orders o
            WHERE o.invoice_number IN (
                SELECT invoice_number FROM offline_orders WHERE invoice_number IS NOT NULL
            )
            UNION ALL
            SELECT 
                oo.invoice_number,
                oo.id as offline_order_id,
                'offline_orders' as table_name,
                oo.created_at
            FROM offline_orders oo
            WHERE oo.invoice_number IN (
                SELECT invoice_number FROM orders WHERE invoice_number IS NOT NULL
            )
            ORDER BY invoice_number, created_at
        ");

        if (empty($crossTableConflicts)) {
            $this->info('✅ لا توجد تضاربات في أرقام الفواتير بين الجدولين');
        } else {
            $this->warn("❌ تم العثور على " . count($crossTableConflicts) . " تضارب في أرقام الفواتير:");
            
            $groupedConflicts = [];
            foreach ($crossTableConflicts as $conflict) {
                if (!isset($groupedConflicts[$conflict->invoice_number])) {
                    $groupedConflicts[$conflict->invoice_number] = [];
                }
                $groupedConflicts[$conflict->invoice_number][] = $conflict;
            }
            
            foreach ($groupedConflicts as $invoiceNumber => $conflicts) {
                $this->warn("   رقم الفاتورة: {$invoiceNumber}");
                foreach ($conflicts as $conflict) {
                    $this->warn("     - {$conflict->table_name}: ID {$conflict->order_id} | {$conflict->created_at}");
                }
                $this->newLine();
            }
            
            if ($shouldFix) {
                $this->fixCrossTableConflicts($groupedConflicts);
            }
        }
    }

    /**
     * إصلاح الطلبات المتطابقة تماماً
     */
    private function fixDuplicateContentOrders($duplicateContent)
    {
        $this->info('🔧 إصلاح الطلبات المتطابقة تماماً...');
        
        $fixedCount = 0;
        foreach ($duplicateContent as $dup) {
            try {
                // الاحتفاظ بالطلب الأقدم وحذف الأحدث
                $orderToDelete = Order::find($dup->order2_id);
                if ($orderToDelete) {
                    // حذف عناصر الطلب أولاً
                    $orderToDelete->items()->delete();
                    
                    // حذف الطلب
                    $orderToDelete->delete();
                    
                    $this->info("   ✅ تم حذف الطلب المكرر {$dup->order2_id}");
                    $fixedCount++;
                }
            } catch (\Exception $e) {
                $this->error("   ❌ فشل في حذف الطلب {$dup->order2_id}: " . $e->getMessage());
            }
        }
        
        $this->info("✅ تم إصلاح {$fixedCount} طلب مكرر");
    }

    /**
     * إصلاح تضارب الأرقام بين الجدولين
     */
    private function fixCrossTableConflicts($groupedConflicts)
    {
        $this->info('🔧 إصلاح تضارب الأرقام بين الجدولين...');
        
        $fixedCount = 0;
        foreach ($groupedConflicts as $invoiceNumber => $conflicts) {
            try {
                // إعادة ترقيم الطلبات الأوفلاين لتجنب التضارب
                foreach ($conflicts as $conflict) {
                    if ($conflict->table_name === 'offline_orders') {
                        $offlineOrder = OfflineOrder::find($conflict->order_id);
                        if ($offlineOrder) {
                            $newInvoiceNumber = \App\Services\InvoiceNumberService::generateInvoiceNumber();
                            $offlineOrder->update(['invoice_number' => $newInvoiceNumber]);
                            
                            $this->info("   ✅ تم تغيير رقم فاتورة الطلب الأوفلاين {$conflict->order_id} من {$invoiceNumber} إلى {$newInvoiceNumber}");
                            $fixedCount++;
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("   ❌ فشل في إصلاح تضارب {$invoiceNumber}: " . $e->getMessage());
            }
        }
        
        $this->info("✅ تم إصلاح {$fixedCount} تضارب في أرقام الفواتير");
    }
} 