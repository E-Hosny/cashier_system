<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OfflineOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class CleanupOfflineOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'offline:cleanup {--dry-run : معاينة بدون تطبيق} {--force : إجبار التنظيف بدون تأكيد}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'تنظيف الطلبات الأوفلاين المعلقة والمكررة';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        $this->info("تنظيف الطلبات الأوفلاين المعلقة والمكررة");
        $this->newLine();
        
        // 1. البحث عن الطلبات المكررة (نفس رقم الفاتورة)
        $this->checkDuplicateOrders($dryRun, $force);
        
        // 2. البحث عن الطلبات المعلقة في حالة "syncing" لفترة طويلة
        $this->checkStuckSyncingOrders($dryRun, $force);
        
        // 3. البحث عن الطلبات الأوفلاين المزامنة مسبقاً
        $this->checkAlreadySyncedOrders($dryRun, $force);
        
        // 4. البحث عن العناصر المكررة
        $this->checkDuplicateItems($dryRun, $force);
        
        return 0;
    }
    
    /**
     * فحص الطلبات المكررة
     */
    private function checkDuplicateOrders($dryRun, $force)
    {
        $this->info("🔍 فحص الطلبات المكررة:");
        
        // البحث عن أرقام فواتير مكررة بين الطلبات الأوفلاين والعادية
        $duplicates = DB::select("
            SELECT 
                o1.invoice_number,
                COUNT(*) as total_count,
                SUM(CASE WHEN o1.table_name = 'orders' THEN 1 ELSE 0 END) as orders_count,
                SUM(CASE WHEN o1.table_name = 'offline_orders' THEN 1 ELSE 0 END) as offline_orders_count
            FROM (
                SELECT invoice_number, 'orders' as table_name FROM orders WHERE invoice_number IS NOT NULL
                UNION ALL
                SELECT invoice_number, 'offline_orders' as table_name FROM offline_orders WHERE invoice_number IS NOT NULL
            ) o1
            GROUP BY o1.invoice_number
            HAVING COUNT(*) > 1
            ORDER BY o1.invoice_number
        ");
        
        if (empty($duplicates)) {
            $this->info("  ✅ لا توجد فواتير مكررة");
            return;
        }
        
        $this->warn("  ⚠️  وجدت " . count($duplicates) . " رقم فاتورة مكرر:");
        
        foreach ($duplicates as $duplicate) {
            $this->line("    - {$duplicate->invoice_number}: {$duplicate->total_count} مرة " .
                       "({$duplicate->orders_count} عادي، {$duplicate->offline_orders_count} أوفلاين)");
        }
        
        if (!$dryRun) {
            if ($force || $this->confirm('هل تريد إصلاح الطلبات المكررة؟')) {
                $this->fixDuplicateOrders($duplicates);
            }
        }
        
        $this->newLine();
    }
    
    /**
     * فحص الطلبات المعلقة في حالة syncing
     */
    private function checkStuckSyncingOrders($dryRun, $force)
    {
        $this->info("🔍 فحص الطلبات المعلقة في المزامنة:");
        
        // البحث عن طلبات في حالة "syncing" لأكثر من ساعة
        $stuckOrders = OfflineOrder::where('status', 'syncing')
            ->where('sync_attempted_at', '<', now()->subHour())
            ->get();
        
        if ($stuckOrders->isEmpty()) {
            $this->info("  ✅ لا توجد طلبات معلقة في المزامنة");
            return;
        }
        
        $this->warn("  ⚠️  وجدت " . $stuckOrders->count() . " طلب معلق في المزامنة:");
        
        foreach ($stuckOrders as $order) {
            $this->line("    - {$order->offline_id} ({$order->invoice_number}) - آخر محاولة: {$order->sync_attempted_at}");
        }
        
        if (!$dryRun) {
            if ($force || $this->confirm('هل تريد إعادة تعيين حالة الطلبات المعلقة؟')) {
                foreach ($stuckOrders as $order) {
                    $order->updateSyncStatus('pending_sync');
                }
                $this->info("  ✅ تم إعادة تعيين " . $stuckOrders->count() . " طلب");
            }
        }
        
        $this->newLine();
    }
    
    /**
     * فحص الطلبات الأوفلاين المزامنة مسبقاً
     */
    private function checkAlreadySyncedOrders($dryRun, $force)
    {
        $this->info("🔍 فحص الطلبات الأوفلاين المزامنة مسبقاً:");
        
        $alreadySynced = OfflineOrder::whereIn('status', ['pending_sync', 'syncing'])
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('orders')
                      ->whereRaw('orders.invoice_number = offline_orders.invoice_number');
            })
            ->get();
        
        if ($alreadySynced->isEmpty()) {
            $this->info("  ✅ لا توجد طلبات أوفلاين مزامنة مسبقاً");
            return;
        }
        
        $this->warn("  ⚠️  وجدت " . $alreadySynced->count() . " طلب أوفلاين مزامن مسبقاً:");
        
        foreach ($alreadySynced as $order) {
            $this->line("    - {$order->offline_id} ({$order->invoice_number})");
        }
        
        if (!$dryRun) {
            if ($force || $this->confirm('هل تريد تحديث حالة الطلبات المزامنة مسبقاً؟')) {
                foreach ($alreadySynced as $order) {
                    $order->updateSyncStatus('synced');
                }
                $this->info("  ✅ تم تحديث " . $alreadySynced->count() . " طلب");
            }
        }
        
        $this->newLine();
    }
    
    /**
     * فحص العناصر المكررة
     */
    private function checkDuplicateItems($dryRun, $force)
    {
        $this->info("🔍 فحص العناصر المكررة:");
        
        $duplicateItems = DB::select("
            SELECT 
                order_id,
                product_id,
                product_name,
                COUNT(*) as count
            FROM order_items 
            GROUP BY order_id, product_id, product_name, quantity, price
            HAVING COUNT(*) > 1
            ORDER BY order_id, product_id
        ");
        
        if (empty($duplicateItems)) {
            $this->info("  ✅ لا توجد عناصر مكررة");
            return;
        }
        
        $this->warn("  ⚠️  وجدت " . count($duplicateItems) . " عنصر مكرر:");
        
        foreach ($duplicateItems as $item) {
            $this->line("    - الطلب {$item->order_id}: {$item->product_name} ({$item->count} مرة)");
        }
        
        if (!$dryRun) {
            if ($force || $this->confirm('هل تريد حذف العناصر المكررة؟')) {
                $this->removeDuplicateItems($duplicateItems);
            }
        }
        
        $this->newLine();
    }
    
    /**
     * إصلاح الطلبات المكررة
     */
    private function fixDuplicateOrders($duplicates)
    {
        $fixed = 0;
        
        foreach ($duplicates as $duplicate) {
            // البحث عن الطلب العادي والأوفلاين بنفس رقم الفاتورة
            $regularOrder = Order::where('invoice_number', $duplicate->invoice_number)->first();
            $offlineOrders = OfflineOrder::where('invoice_number', $duplicate->invoice_number)->get();
            
            if ($regularOrder && $offlineOrders->count() > 0) {
                // إذا كان هناك طلب عادي، تحديث حالة الطلبات الأوفلاين إلى مزامنة
                foreach ($offlineOrders as $offlineOrder) {
                    $offlineOrder->updateSyncStatus('synced');
                    $fixed++;
                }
            }
        }
        
        $this->info("  ✅ تم إصلاح {$fixed} طلب مكرر");
    }
    
    /**
     * حذف العناصر المكررة
     */
    private function removeDuplicateItems($duplicateItems)
    {
        $removed = 0;
        
        foreach ($duplicateItems as $item) {
            // الاحتفاظ بأول عنصر وحذف الباقي
            $items = OrderItem::where('order_id', $item->order_id)
                             ->where('product_id', $item->product_id)
                             ->where('product_name', $item->product_name)
                             ->orderBy('id')
                             ->get();
            
            // حذف العناصر الإضافية (الاحتفاظ بالأول)
            for ($i = 1; $i < $items->count(); $i++) {
                $items[$i]->delete();
                $removed++;
            }
        }
        
        $this->info("  ✅ تم حذف {$removed} عنصر مكرر");
    }
} 